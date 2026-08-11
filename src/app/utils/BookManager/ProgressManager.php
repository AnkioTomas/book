<?php

declare(strict_types=1);

namespace app\utils\BookManager;

use nova\framework\core\File;

class ProgressManager extends BaseManager
{
    private string $progress;
    public function __construct()
    {
        parent::__construct();
        $this->progress = $this->runtime . "progress" . DS;
        File::mkDir($this->progress);
    }

    /**
     * 获取阅读进度文本。
     *
     * @param string $filename 书籍相对路径或文件名（sidecar 只用 basename）
     */
    public function getProgressText(string $filename): string
    {
        $key = $this->sidecarKey($filename);
        if ($key === '') {
            return '';
        }

        $remotePath = $this->moon . '/Cache/' . $key . '.po';
        $localPath = $this->progress . md5($key) . ".po";

        if ($this->client->download($remotePath, $localPath)) {
            return (string)file_get_contents($localPath);
        }
        return '';
    }

    public function uploadProgressText(string $filename, string $content): bool
    {
        $key = $this->sidecarKey($filename);
        $content = trim($content);
        if ($key === '' || $content === '') {
            return false;
        }
        if (!$this->ensureRemoteDir($this->moon . '/Cache')) {
            return false;
        }

        $remotePath = $this->moon . '/Cache/' . $key . '.po';
        $localPath = $this->progress . md5($key) . ".po";
        File::write($localPath, $content);

        return $this->client->upload($localPath, $remotePath);
    }

    /**
     * 列出远端进度目录下所有 .po 文件 → 最后修改时间(秒) 的映射。
     * key 为还原后的书籍 sidecar 名（去掉 .po 后缀，即 basename）。
     * 用于增量同步：仅下载 mtime 比上次同步更新的进度文件。
     * 返回 null 表示无法获取（目录不存在或网络失败），调用方应跳过进度拉取。
     *
     * @return array<string,int>|null
     */
    public function listRemoteProgress(): ?array
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
            if ($name === '' || !str_ends_with($name, '.po')) {
                continue;
            }
            $filename = substr($name, 0, -3);
            $map[$filename] = (int)($f['mtime'] ?? 0);
        }
        return $map;
    }

    /**
     * 进度 sidecar 改名（basename 变了才需要）。
     */
    public function moveProgress(string $from, string $to, bool $overwrite = false): bool
    {
        $srcKey = $this->sidecarKey($from);
        $dstKey = $this->sidecarKey($to);
        if ($srcKey === '' || $dstKey === '' || $srcKey === $dstKey) {
            return true;
        }
        $src = $this->moon . '/Cache/' . $srcKey . '.po';
        $dst = $this->moon . '/Cache/' . $dstKey . '.po';
        return $this->client->move($src, $dst, $overwrite);
    }

    public function deleteProgress(string $filename): void
    {
        $key = $this->sidecarKey($filename);
        if ($key === '') {
            return;
        }
        $remotePath = $this->moon . '/Cache/' . $key . '.po';
        try {
            $this->client->delete($remotePath);
        } catch (\RuntimeException $exception) {
            // 无论如何删除失败都不抛出异常
        }
    }

}
