<?php

declare(strict_types=1);

namespace app\utils\BookManager;

use nova\framework\core\File;

/**
 * 静读天下注解 sidecar：`.Moon+/Cache/<basename>.an`
 * 与 ProgressManager 同目录布局；本类只负责下载/列举/改名/删除，不解析内容。
 */
class AnnotationManager extends BaseManager
{
    private string $localDir;

    public function __construct()
    {
        parent::__construct();
        $this->localDir = $this->runtime . 'annotations' . DS;
        File::mkDir($this->localDir);
    }

    /**
     * 列出远端 Cache 下所有 .an → mtime(秒)。
     * key 为 sidecar basename（去掉 .an）。
     * null = 目录不可达。
     *
     * @return array<string,int>|null
     */
    public function listRemoteAnnotations(): ?array
    {
        $dir = $this->moon . '/Cache';
        if (!$this->ensureRemoteDir($dir)) {
            return null;
        }
        try {
            $files = $this->client->listDir($dir);
        } catch (\Throwable $e) {
            return null;
        }

        $map = [];
        foreach ($files as $f) {
            if (!empty($f['is_dir'])) {
                continue;
            }
            $name = $f['name'] ?? '';
            if ($name === '' || !str_ends_with(strtolower($name), '.an')) {
                continue;
            }
            $key = substr($name, 0, -3);
            $map[$key] = (int)($f['mtime'] ?? 0);
        }
        return $map;
    }

    /** 下载 .an 二进制；失败返回空串。 */
    public function getAnnotationBytes(string $filename): string
    {
        $key = $this->sidecarKey($filename);
        if ($key === '') {
            return '';
        }

        $remotePath = $this->moon . '/Cache/' . $key . '.an';
        $localPath = $this->localDir . md5($key) . '.an';

        if ($this->client->download($remotePath, $localPath)) {
            $bytes = @file_get_contents($localPath);
            return is_string($bytes) ? $bytes : '';
        }
        return '';
    }

    public function moveAnnotation(string $from, string $to, bool $overwrite = false): bool
    {
        $srcKey = $this->sidecarKey($from);
        $dstKey = $this->sidecarKey($to);
        if ($srcKey === '' || $dstKey === '' || $srcKey === $dstKey) {
            return true;
        }
        $src = $this->moon . '/Cache/' . $srcKey . '.an';
        $dst = $this->moon . '/Cache/' . $dstKey . '.an';
        return $this->client->move($src, $dst, $overwrite);
    }

    public function deleteAnnotation(string $filename): void
    {
        $key = $this->sidecarKey($filename);
        if ($key === '') {
            return;
        }
        $remotePath = $this->moon . '/Cache/' . $key . '.an';
        try {
            $this->client->delete($remotePath);
        } catch (\RuntimeException $exception) {
            // 远端没有 sidecar 时静默
        }
    }
}
