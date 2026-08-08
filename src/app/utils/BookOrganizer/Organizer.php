<?php

declare(strict_types=1);

namespace app\utils\BookOrganizer;

use app\database\dao\BookDao;
use app\database\dao\ReadingProgressDao;
use app\database\model\BookModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookManager\ProgressManager;
use nova\framework\core\Logger;
use Throwable;

/**
 * 单本整理：算路径 → MOVE 书/封面/进度 → 改 DB。
 */
class Organizer
{
    /**
     * @param  BookModel[]                                                            $books
     * @param  array<string,true>|null                                                $taken
     * @return list<array{id:int,bookName:string,from:string,to:string,changed:bool}>
     */
    public static function preview(array $books, ?array $taken = null): array
    {
        $bm = BookManager::getInstance();
        if ($taken === null) {
            $taken = [];
            foreach ($books as $b) {
                $rel = $bm->normalizeBookPath($b->filename);
                if ($rel !== '') {
                    $taken[$rel] = true;
                }
            }
        }

        $rows = [];
        foreach ($books as $book) {
            $from = $bm->normalizeBookPath($book->filename);
            $desired = Namer::targetPath($book);
            $pool = $taken;
            unset($pool[$from]);
            $to = Namer::resolveConflict($desired, $pool);
            $taken[$to] = true;

            $rows[] = [
                'id' => (int)$book->id,
                'bookName' => $book->bookName !== '' ? $book->bookName : $from,
                'from' => $from,
                'to' => $to,
                'changed' => $from !== $to,
            ];
        }
        return $rows;
    }

    /**
     * @param  array<string,true>                                           $taken
     * @return array{ok:bool,from:string,to:string,skipped:bool,msg:string}
     */
    public static function organizeOne(BookModel $book, array &$taken): array
    {
        $bm = BookManager::getInstance();
        $from = $bm->normalizeBookPath($book->filename);
        if ($from === '') {
            return [
                'ok' => false,
                'from' => '',
                'to' => '',
                'skipped' => false,
                'msg' => '无效文件名（' . ($book->filename === '' ? '文件名为空' : $book->filename) . '）',
            ];
        }

        $desired = Namer::targetPath($book);
        $pool = $taken;
        unset($pool[$from]);
        $to = Namer::resolveConflict($desired, $pool);

        if ($from === $to) {
            $taken[$to] = true;
            return ['ok' => true, 'from' => $from, 'to' => $to, 'skipped' => true, 'msg' => '已在目标路径'];
        }

        try {
            if (!$bm->bookExists($from)) {
                return ['ok' => false, 'from' => $from, 'to' => $to, 'skipped' => false, 'msg' => '远端源文件不存在'];
            }

            if ($bm->bookExists($to)) {
                $n = 2;
                $dir = dirname($to);
                $ext = pathinfo($to, PATHINFO_EXTENSION);
                $stem = pathinfo($to, PATHINFO_FILENAME);
                $prefix = ($dir === '.' || $dir === '') ? '' : $dir . '/';
                while ($bm->bookExists($prefix . $stem . ' (' . $n . ').' . $ext)) {
                    $n++;
                    if ($n > 9999) {
                        return ['ok' => false, 'from' => $from, 'to' => $to, 'skipped' => false, 'msg' => '目标冲突无法消解'];
                    }
                }
                $to = $prefix . $stem . ' (' . $n . ').' . $ext;
            }

            if (!$bm->moveBook($from, $to, false)) {
                return ['ok' => false, 'from' => $from, 'to' => $to, 'skipped' => false, 'msg' => 'WebDAV MOVE 书文件失败'];
            }

            if ($bm->sidecarKey($from) !== $bm->sidecarKey($to)) {
                try {
                    CoverManager::getInstance()->moveCover($from, $to, false);
                } catch (Throwable $e) {
                    Logger::warning('[Organizer] moveCover: ' . $e->getMessage());
                }
                try {
                    ProgressManager::getInstance()->moveProgress($from, $to, false);
                } catch (Throwable $e) {
                    Logger::warning('[Organizer] moveProgress: ' . $e->getMessage());
                }
            }

            $book->filename = $to;
            $book->downloadUrl = $bm->downloadUrlFor($to);
            $book->update_at = time() * 1000;
            BookDao::getInstance()->updateModel($book);
            ReadingProgressDao::getInstance()->renameFilename($from, $to);

            $taken[$to] = true;
            unset($taken[$from]);

            return ['ok' => true, 'from' => $from, 'to' => $to, 'skipped' => false, 'msg' => '已整理'];
        } catch (Throwable $e) {
            Logger::error('[Organizer] organize failed: ' . $e->getMessage());
            return ['ok' => false, 'from' => $from, 'to' => $to, 'skipped' => false, 'msg' => $e->getMessage()];
        }
    }
}
