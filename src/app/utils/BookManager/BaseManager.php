<?php

namespace app\utils\BookManager;

use nova\framework\core\File;
use nova\framework\core\Instance;
use nova\plugin\webdav\SimpleWebDAVClient;
use function nova\framework\config;

class BaseManager extends Instance
{

    protected SimpleWebDAVClient $client;

    protected string $deviceId = "";

    protected string $path = "/Apps/Books";

    protected string $moon = "/Apps/Books/.Moon+";

    protected string $runtime = "";
    public function __construct()
    {
        $this->runtime = RUNTIME_PATH . DS . "books" . DS;
        File::mkDir($this->runtime);
        $url = config('webdav.url');
        $username = config('webdav.username');
        $password = config('webdav.password');
        $this->deviceId = config('webdav.deviceId') ?? "";
        if (empty($this->deviceId)){
            $this->deviceId = (string)(time() * 1000);
            config('webdav.deviceId',$this->deviceId);
        }

        $this->client = new SimpleWebDAVClient($url, $username, $password);
    }

    public function __destruct()
    {

    }

    /**
     * 规范化文件名（跨平台可用 + URL 友好，简化版）。
     */
    public function normalizeFilename(string $filename): string
    {
        // 1. 基础清理：去空格、路径符号转横线
        $filename = str_replace(['%'], '-', trim($filename));

        // 6. 直接返回
        return $filename;
    }


}