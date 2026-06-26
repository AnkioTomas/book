<?php

declare(strict_types=1);

namespace app\task;

use app\database\dao\BookDao;
use app\database\dao\ReadingProgressDao;
use app\database\model\BookModel;
use app\database\model\ReadingProgressModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookManager\ProgressManager;
use app\utils\Douban;
use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\task\TaskLogger;
use Throwable;

/**
 * 书库增量同步任务（WebDAV ↔ 本地数据库）。
 *
 * 设计要点：
 * - 删除传播以「远端实际 epub 文件列表」为权威信号（一次目录列举），本地有记录但远端文件已消失即删除本地。
 *   无法列出远端目录（网络/认证失败）时整体中止，绝不把「无法确认」当成「不存在」。
 * - 元数据 last-write-wins：本地 update_at > 上次同步时间则本地为准，否则以远端 books.sync 为准。
 * - 进度仲裁复用 .po 串内的 ts 与本地 timestamp，新者胜。
 * - 增量：远端 books.sync 文件 mtime 未变则跳过远端书目处理；进度仅下载 mtime 比上次同步更新的 .po；
 *   仅在本地或远端确有变化时才回写 books.sync。
 * - books.sync / .po 为移动端共享契约；写入完整 BookModel 字段（groupBooks 为数组、series/seriesNum 独立），
 *   仅本地仲裁用的 update_at 不写入。
 */
class SyncTask extends TaskerAbstract
{
    private const STATE_LAST_MS = 'book.sync.last_ms';
    private const STATE_META_MTIME = 'book.sync.meta_mtime';

    public function getTimeOut(): int
    {
        return 600;
    }

    public function onStart(): void
    {
        $cache = Context::instance()->cache;
        $lastMs = (int)$cache->get(self::STATE_LAST_MS, 0);
        $savedMetaMtime = (int)$cache->get(self::STATE_META_MTIME, 0);

        // 本次同步的时间基准在开头定格：syncProgress 期间产生的本地新变更，
        // 其 update_at 落在 [nowMs, 结束] 之间，下次同步仍 > lastMs，不会被漏掉。
        $nowMs = time() * 1000;

        $bookManager = BookManager::getInstance();

        // 1. 远端 epub 文件列表 —— 删除判定的唯一可靠依据。null = 不可达 → 中止，绝不删。
        TaskLogger::log('列举远端书库目录…');
        $remoteFiles = $bookManager->listRemoteFilenames();
        if ($remoteFiles === null) {
            Logger::warning('[SyncTask] 无法列出远端书库目录，跳过本次同步以避免误删');
            TaskLogger::log('无法列出远端书库目录，跳过本次同步以避免误删', 'warn');
            $cache->set('sync-books', 'complete');
            return;
        }
        $fileSet = array_fill_keys($remoteFiles, true);

        // 2. 远端 books.sync 文件 mtime，判断远端书目是否变化（增量）。
        $metaMtime = $bookManager->getBookListMtime();
        $remoteMetaChanged = $metaMtime !== null && $metaMtime > $savedMetaMtime;

        // 3. 本地书目。
        /** @var BookModel[] $localBooks */
        $localBooks = BookDao::getInstance()->select()->commit();
        $localMap = [];
        foreach ($localBooks as $b) {
            $localMap[$b->filename] = $b;
        }
        TaskLogger::log('远端文件 ' . count($remoteFiles) . ' 个，本地记录 ' . count($localMap) . ' 条');

        // 防误删：远端目录为空而本地非空，视为可疑状态，本轮不删除。
        $allowDelete = !(empty($fileSet) && !empty($localMap));
        if (!$allowDelete) {
            Logger::warning('[SyncTask] 远端文件列表为空而本地非空，跳过删除传播');
        }

        $dirty = false;

        // 4. 删除传播：本地有记录但远端 epub 已消失 → 删本地。
        $deleted = 0;
        if ($allowDelete) {
            foreach ($localMap as $filename => $book) {
                if (isset($fileSet[$filename])) {
                    continue;
                }
                $this->deleteLocal($book);
                unset($localMap[$filename]);
                $dirty = true;
                $deleted++;
            }
        }
        TaskLogger::log('删除本地 ' . $deleted . ' 本（远端已移除）');

        // 5. 远端 → 本地：以「远端 epub 真实列表」为唯一驱动源。
        //    - 本地缺失：有元数据→拉入；无元数据(裸 epub)→按文件名补全，回写时收录进 books.sync。
        //    - 已存在 + 远端书目更新 + 本地未改：以远端元数据为准。
        //    仅在「远端书目变化」或「存在本地缺失文件」时才下载 books.sync。
        $missing = false;
        foreach ($remoteFiles as $filename) {
            if ($filename !== '' && !isset($localMap[$filename])) {
                $missing = true;
                break;
            }
        }

        $metaMap = [];
        if ($remoteMetaChanged || $missing) {
            $remoteList = $bookManager->list();
            if ($remoteList === null) {
                TaskLogger::log('无法下载远端书目文件，跳过元数据比对（不影响裸文件补全）', 'warn');
            } else {
                foreach ($remoteList as $entry) {
                    $fn = $entry['filename'] ?? '';
                    if ($fn !== '') {
                        $metaMap[$fn] = $entry;
                    }
                }
            }
        }

        $pulled = 0;
        $bare = 0;
        foreach ($remoteFiles as $filename) {
            if ($filename === '') {
                continue;
            }
            $local = $localMap[$filename] ?? null;
            $entry = $metaMap[$filename] ?? null;

            if ($local === null) {
                if ($entry !== null) {
                    $localMap[$filename] = $this->pullRemote($entry);
                    $pulled++;
                } else {
                    $localMap[$filename] = $this->pullBare($filename);
                    $bare++;
                }
                $dirty = true;
            } elseif ($remoteMetaChanged && $entry !== null && $local->update_at <= $lastMs) {
                $localMap[$filename] = $this->pullRemote($entry);
                $dirty = true;
                $pulled++;
            }
            // 本地 update_at > lastMs：本地改动优先，保留本地，稍后推送
        }
        TaskLogger::log('从远端拉取/更新 ' . $pulled . ' 本，裸文件补全 ' . $bare . ' 本');

        // 6. 本地新增/编辑（update_at > lastMs）需要推送到远端 books.sync。
        if (!$dirty) {
            foreach ($localMap as $book) {
                if ($book->update_at > $lastMs) {
                    $dirty = true;
                    break;
                }
            }
        }

        // 7. 进度增量同步（双向）。
        TaskLogger::log('同步阅读进度…');
        [$progressDown, $progressUp] = $this->syncProgress(array_keys($localMap), $lastMs);
        TaskLogger::log('进度：下载 ' . $progressDown . ' 条，上传 ' . $progressUp . ' 条');

        // 8. 封面补传（沿用：本地标记过需要上传封面的书）。
        $covers = $this->syncCovers($localMap, $cache);
        TaskLogger::log('封面补传 ' . $covers . ' 张');

        // 9. 回写 books.sync —— 仅在确有变化时（增量）。
        if ($dirty || $remoteMetaChanged) {
            TaskLogger::log('回写 books.sync（' . count($localMap) . ' 条）');
            $payload = [];
            foreach ($localMap as $book) {
                $payload[] = $this->toRemoteEntry($book);
            }
            try {
                $bookManager->updateBookList($payload);
            } catch (Throwable $e) {
                Logger::error('[SyncTask] 回写 books.sync 失败: ' . $e->getMessage());
                TaskLogger::log('回写 books.sync 失败: ' . $e->getMessage(), 'error');
            }
        } else {
            TaskLogger::log('无变化，跳过回写');
        }

        // 10. 更新增量状态。lastMs 用本轮开头定格的 nowMs，消除 syncProgress 期间的丢更新窗口。
        $cache->set(self::STATE_LAST_MS, $nowMs, 0);
        // books.sync 的 mtime：回写过才重新获取（远端已变），否则沿用本轮开头读到的值，省一次远端往返。
        if ($dirty || $remoteMetaChanged) {
            $newMeta = $bookManager->getBookListMtime();
            $cache->set(self::STATE_META_MTIME, $newMeta ?? $metaMtime ?? 0, 0);
        } else {
            $cache->set(self::STATE_META_MTIME, $metaMtime ?? $savedMetaMtime, 0);
        }
    }

    /**
     * 删除本地书籍记录及其进度（远端文件已被删除，本地仅清理本地数据）。
     */
    private function deleteLocal(BookModel $book): void
    {
        BookDao::getInstance()->deleteById($book->id);
        ReadingProgressDao::getInstance()->delete()->where(['filename' => $book->filename])->commit();
    }

    /**
     * 用远端 books.sync 条目覆盖/新建本地记录（按 filename upsert）。
     */
    private function pullRemote(array $entry): BookModel
    {
        $book = new BookModel($entry);
        $book->update_at = 0; // 来自远端，标记为本地未修改
        BookDao::getInstance()->insertModel($book, true);
        return $book;
    }

    /**
     * 裸 epub 补全：远端存在 epub 文件但 books.sync 无对应元数据。
     * 以文件名兜底建本地记录，回写时一并收录进 books.sync，使远端书目自愈收敛。
     */
    private function pullBare(string $filename): BookModel
    {
        $book = new BookModel();
        $book->filename = $filename;
        $book->bookName = preg_replace('/\.[^.]+$/', '', $filename);
        $book->update_at = 0; // 视为远端来源，避免被当作本地编辑
        BookDao::getInstance()->insertModel($book, true);
        return $book;
    }

    /**
     * 进度增量同步：仅处理变化的 .po（远端 mtime 新于上次同步、或本地阅读时间新于上次同步）。
     *
     * @param  string[]           $filenames 当前保留的书籍文件名
     * @return array{0:int,1:int} [下载条数, 上传条数]
     */
    private function syncProgress(array $filenames, int $lastMs): array
    {
        $kept = array_fill_keys($filenames, true);
        $progressManager = ProgressManager::getInstance();
        $poMap = $progressManager->listRemoteProgress();

        $down = 0;
        $up = 0;

        // 远端 → 本地：仅下载 mtime 更新的 .po。
        if ($poMap !== null) {
            foreach ($poMap as $filename => $mtimeSec) {
                if (!isset($kept[$filename]) || $mtimeSec * 1000 <= $lastMs) {
                    continue;
                }
                $raw = $progressManager->getProgressText($filename);
                if (!$raw) {
                    continue;
                }
                $remote = ReadingProgressModel::fromString($raw);
                $remote->filename = $filename;
                $local = ReadingProgressDao::getInstance()->getByFilename($filename);
                if ($local === null || $remote->timestamp > $local->timestamp) {
                    ReadingProgressDao::getInstance()->insertModel($remote, true);
                    $down++;
                }
            }
        }

        // 本地 → 远端：仅上传上次同步后有新阅读的进度。
        $localNew = ReadingProgressDao::getInstance()->getUpdatedSince($lastMs);
        foreach ($localNew as $p) {
            if (!isset($kept[$p->filename])) {
                continue;
            }
            $remoteMs = isset($poMap[$p->filename]) ? $poMap[$p->filename] * 1000 : 0;
            if ($p->timestamp > $remoteMs) {
                if ($progressManager->uploadProgressText($p->filename, $p->toString())) {
                    $up++;
                }
            }
        }

        return [$down, $up];
    }

    /**
     * 封面补传：编辑书籍时标记了 coverUrl 待同步的，下载豆瓣封面并上传 WebDAV。
     *
     * @param  BookModel[] $localMap
     * @return int         成功补传的封面数量
     */
    private function syncCovers(array $localMap, $cache): int
    {
        $count = 0;
        foreach ($localMap as $book) {
            if (empty($book->coverUrl) || !$cache->get("coverUrl/{$book->coverUrl}")) {
                continue;
            }
            try {
                $file = Douban::download($book->coverUrl);
                if ($file && CoverManager::getInstance()->uploadCover($file, $book->filename)) {
                    $cache->delete("coverUrl/{$book->coverUrl}");
                    $count++;
                }
            } catch (Throwable $e) {
                Logger::warning('[SyncTask] 封面上传失败 ' . $book->filename . ': ' . $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * 序列化为 books.sync 条目。
     *
     * toArray(false) 跳过 Model::onToArray 的 serialize，使 groupBooks 保持为 JSON 数组
     * （否则会被写成 "a:0:{}" 这类 PHP 序列化串，移动端无法解析）。
     * series / seriesNum 作为独立字段输出，category 保持纯标签，与移动端契约一致。
     * update_at 为本地同步仲裁字段，不写入共享文件。
     */
    private function toRemoteEntry(BookModel $book): array
    {
        $entry = $book->toArray(false);
        unset($entry['update_at']);
        return $entry;
    }

    public function onStop(): void
    {
        Context::instance()->cache->set('sync-books', 'complete');
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[SyncTask] 同步异常中止: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        Context::instance()->cache->set('sync-books', 'complete');
    }
}
