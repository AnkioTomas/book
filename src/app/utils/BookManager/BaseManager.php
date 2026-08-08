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
     * 规范化书籍相对路径：允许「文件名」或「分类/文件名」。
     * 只拒绝路径段 `..`，不拦文件名里的 `...`（Z-Library 截断很常见）。
     */
    public function normalizeBookPath(string $filename): string
    {
        $filename = trim(str_replace(['\\', '%'], ['/', '-'], $filename), '/');
        if ($filename === '') {
            return '';
        }

        $parts = [];
        foreach (explode('/', $filename) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        if ($parts === []) {
            return '';
        }
        if (count($parts) > 2) {
            $parts = array_slice($parts, -2);
        }
        return implode('/', $parts);
    }

    /** Moon+ Cover/Cache 键：basename。 */
    public function sidecarKey(string $filename): string
    {
        $path = $this->normalizeBookPath($filename);
        return $path === '' ? '' : basename($path);
    }

    /**
     * @deprecated 用 normalizeBookPath 或 sidecarKey
     */
    public function normalizeFilename(string $filename): string
    {
        return $this->sidecarKey($filename);
    }

    public function bookRemotePath(string $filename): string
    {
        $rel = $this->normalizeBookPath($filename);
        return $rel === '' ? '' : $this->path . '/' . $rel;
    }

    public function downloadUrlFor(string $filename): string
    {
        $rel = $this->normalizeBookPath($filename);
        return $rel === '' ? '' : '[WebDav]' . $this->path . '/' . $rel;
    }

    public function getClient(): SimpleWebDAVClient
    {
        return $this->client;
    }
}
