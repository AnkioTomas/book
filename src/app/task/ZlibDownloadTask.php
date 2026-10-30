<?php

declare(strict_types=1);

namespace app\task;

use app\utils\BookIngest;
use app\utils\meta\MetaHit;
use app\utils\zlib\ZlibClient;
use nova\framework\core\Logger;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\corn\schedule\TaskerManager;
use nova\plugin\corn\schedule\TaskerTime;
use nova\plugin\task\TaskLogger;
use RuntimeException;
use Throwable;

use const ROOT_PATH;

/**
 * Z站下载入库：后台拉取文件并写入书库，进度进任务面板。
 * 命中下载限额时，自动改约到次日 20:54 再试。
 */
class ZlibDownloadTask extends TaskerAbstract
{
    public const string DOWNLOAD_PREFIX = 'Z站下载_';
    public const string DELAYED_PREFIX = 'Z站延后下载_';

    public function __construct(
        private readonly string $id,
        private readonly string $hash,
        private readonly string $title = '',
        private readonly string $author = '',
        private readonly string $extension = 'epub',
    ) {
    }

    public function getTimeOut(): int
    {
        return 600;
    }

    public function onStart(): void
    {
        $client = ZlibClient::getInstance();
        $title = trim($this->title);
        $author = MetaHit::cleanAuthor($this->author);
        $ext = strtolower(trim($this->extension));
        if ($ext === '') {
            $ext = 'epub';
        }

        $label = $title !== '' ? '《' . $title . '》' : ($this->id . ':' . $this->hash);
        TaskLogger::log($label . ' 开始下载…');

        $tmp = '';
        try {
            if ($title === '') {
                $detail = $client->detail($this->id, $this->hash);
                $title = (string)($detail['title'] ?? 'book');
                if ($author === '') {
                    $author = MetaHit::cleanAuthor((string)($detail['author'] ?? ''));
                }
                if ($ext === 'epub') {
                    $ext = (string)($detail['extension'] ?? 'epub');
                }
                $label = '《' . $title . '》';
            }

            $filename = ZlibClient::safeFilename($title, $author, $ext);
            $tmpDir = ROOT_PATH . '/runtime/zlib';
            if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
                throw new RuntimeException('无法创建临时目录');
            }
            $tmp = $tmpDir . '/' . md5($this->id . ':' . $this->hash . microtime(true))
                . '.' . pathinfo($filename, PATHINFO_EXTENSION);

            TaskLogger::log($label . ' 拉取文件 → ' . $filename);
            $client->download($this->id, $this->hash, $tmp);

            TaskLogger::log($label . ' 入库中…');
            $result = BookIngest::fromLocalFile($tmp, $filename);
            @unlink($tmp);
            $tmp = '';

            if (!$result['ok']) {
                TaskLogger::log($label . ' 入库失败：' . $result['msg'], 'error');
                return;
            }

            $msg = $result['msg'];
            if (!empty($result['isNew'])) {
                $msg .= '（新书）';
            }
            TaskLogger::log($label . ' ' . $msg);
        } catch (Throwable $e) {
            if ($tmp !== '' && is_file($tmp)) {
                @unlink($tmp);
            }
            $err = $e->getMessage();
            TaskLogger::log($label . ' 失败：' . $err, 'error');
            Logger::error('[ZlibDownloadTask] ' . $err);
            if (self::isQuotaExceeded($err)) {
                $this->scheduleQuotaRetry($label, $title, $author, $ext);
            }
        }
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[ZlibDownloadTask] 异常中止: ' . $e->getMessage());
        TaskLogger::log('Z站下载异常中止：' . $e->getMessage(), 'error');
        if (self::isQuotaExceeded($e->getMessage())) {
            $this->scheduleQuotaRetry(
                trim($this->title) !== '' ? '《' . trim($this->title) . '》' : ($this->id . ':' . $this->hash),
                trim($this->title),
                MetaHit::cleanAuthor($this->author),
                strtolower(trim($this->extension)) ?: 'epub'
            );
        }
    }

    public static function isQuotaExceeded(string $msg): bool
    {
        return str_contains($msg, '下载限额');
    }

    /** 任务面板显示用短书名：去掉书名号/营销括号，控制长度 */
    public static function shortTitle(string $title, string $fallbackId = ''): string
    {
        $t = trim($title);
        $t = preg_replace('/《([^》]*)》/u', '$1', $t) ?? $t;
        $t = preg_replace('/【[^】]*】/u', '', $t) ?? $t;
        $t = preg_replace('/\[[^\]]*\]/u', '', $t) ?? $t;
        $t = preg_replace('/[\\\\\/:*?"<>|\r\n]+/u', ' ', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
        if ($t === '') {
            $t = $fallbackId !== '' ? $fallbackId : '未知书籍';
        }
        if (mb_strlen($t) > 40) {
            $t = mb_substr($t, 0, 40);
        }
        return $t;
    }

    public static function downloadTaskName(string $title, string $id = ''): string
    {
        return self::DOWNLOAD_PREFIX . self::shortTitle($title, $id);
    }

    public static function delayedTaskName(string $title, string $id = ''): string
    {
        return self::DELAYED_PREFIX . self::shortTitle($title, $id);
    }

    /** 兼容旧版随机后缀任务名，便于手动重下时清掉 */
    public static function legacyDelayedTaskName(string $id, string $hash): string
    {
        return self::DELAYED_PREFIX . substr(md5($id . ':' . $hash), 8, 8);
    }

    public static function legacyDownloadTaskName(string $id, string $hash): string
    {
        return self::DOWNLOAD_PREFIX . substr(md5($id . ':' . $hash), 8, 8);
    }

    /** 次日 20:54 再试同一本书（times=1） */
    private function scheduleQuotaRetry(string $label, string $title, string $author, string $ext): void
    {
        $name = self::delayedTaskName($title, $this->id);
        $at = strtotime('tomorrow 20:54');
        if ($at === false || $at <= time()) {
            $at = time() + 86400;
        }
        $cron = TaskerTime::after(max(60, $at - time()));

        TaskerManager::del($name);
        TaskerManager::del(self::legacyDelayedTaskName($this->id, $this->hash));
        $key = TaskerManager::add(
            $cron,
            new self($this->id, $this->hash, $title, $author, $ext),
            $name,
            1
        );
        if ($key === '') {
            // 同名书冲突时带上 id，保证能入队
            $name = self::DELAYED_PREFIX . self::shortTitle($title, $this->id) . '·' . $this->id;
            TaskerManager::del($name);
            $key = TaskerManager::add(
                $cron,
                new self($this->id, $this->hash, $title, $author, $ext),
                $name,
                1
            );
        }
        if ($key === '') {
            TaskLogger::log($label . ' 限额重试入队失败', 'error');
            return;
        }

        $when = date('Y-m-d H:i', $at);
        TaskLogger::log($label . ' 已达下载限额，已改约到 ' . $when . ' 自动重试（' . $name . '）');
        Logger::info('[ZlibDownloadTask] quota retry scheduled: ' . $name . ' @ ' . $when);
    }
}
