<?php

declare(strict_types=1);

namespace app\utils\BookManager;

use function nova\framework\config;

use nova\framework\core\File;
use nova\framework\core\Instance;
use nova\plugin\webdav\SimpleWebDAVClient;

class BaseManager extends Instance
{
    protected SimpleWebDAVClient $client;

    public string $deviceId = "";

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
        if (empty($this->deviceId)) {
            $this->deviceId = (string)(time() * 1000);
            config('webdav.deviceId', $this->deviceId);
        }

        $this->client = new SimpleWebDAVClient($url, $username, $password);
    }

    public function __destruct()
    {

    }

    /**
     * 规范化文件名：去掉首尾空白，剥掉误带的路径前缀，百分号替换。
     * 不做「美化重命名」——远端文件名是契约，乱改会找不到书。
     */
    public function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);
        // 若误传入带分隔符的路径，只保留末段；分隔符统一按 URL 语义处理
        $filename = str_replace('\\', '/', $filename);
        if (str_contains($filename, '/')) {
            $filename = basename($filename);
        }
        return str_replace('%', '-', $filename);
    }

}
