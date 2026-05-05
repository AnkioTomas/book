<?php

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