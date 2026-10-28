<?php

declare(strict_types=1);

namespace app\task;

use app\utils\BookIngest;
use app\utils\meta\MetaHit;
use app\utils\zlib\ZlibClient;
use nova\framework\core\Logger;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\task\TaskLogger;
use RuntimeException;
use Throwable;

use const ROOT_PATH;

/**
 * Z站下载入库：后台拉取文件并写入书库，进度进任务面板。
 */
class ZlibDownloadTask extends TaskerAbstract
{
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
            TaskLogger::log($label . ' 失败：' . $e->getMessage(), 'error');
            Logger::error('[ZlibDownloadTask] ' . $e->getMessage());
        }
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[ZlibDownloadTask] 异常中止: ' . $e->getMessage());
        TaskLogger::log('Z站下载异常中止：' . $e->getMessage(), 'error');
    }
}
