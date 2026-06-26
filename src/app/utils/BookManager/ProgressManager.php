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
     * @param string $filename 文件名
      */
    public function getProgressText(string $filename): string
    {

        $remotePath = $this->moon . DS . "Cache" . DS . $filename . ".po";

        $localPath = $this->progress . md5($filename) . ".po";

        if ($this->client->download($remotePath, $localPath)) {
            return file_get_contents($localPath);
        } else {
            return   "";
        }
    }

    public function uploadProgressText(string $filename, string $content): bool
    {
        $filename = trim($filename);
        $content = trim($content);
        if ($filename === '' || $content === '') {
            return false;
        }

        $remotePath = $this->moon . DS . "Cache" . DS . $filename . ".po";

        $localPath = $this->progress . md5($filename) . ".po";
        File::write($localPath, $content);

        if ($this->client->upload($localPath, $remotePath)) {
            return true;
        }
        return false;
    }

    /**
     * 列出远端进度目录下所有 .po 文件 → 最后修改时间(秒) 的映射。
     * key 为还原后的书籍文件名（去掉 .po 后缀）。
     * 用于增量同步：仅下载 mtime 比上次同步更新的进度文件。
     * 返回 null 表示无法获取（目录不存在或网络失败），调用方应跳过进度拉取。
     *
     * @return array<string,int>|null
     */
    public function listRemoteProgress(): ?array
    {
        $dir = $this->moon . DS . "Cache";
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

    public function deleteProgress(string $filename): void
    {
        $remotePath = $this->moon . DS . "Cache" . DS . $filename . ".po";
        try {
            $this->client->delete($remotePath);
        } catch (\RuntimeException $exception) {
            // 无论如何删除失败都不抛出异常
        }
    }

}
