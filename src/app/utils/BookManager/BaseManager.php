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

    /** @var array<string, bool> 本进程内已确认存在的远端目录 */
    private array $ensuredDirs = [];

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
     * 确保远端目录存在（逐级 MKCOL，含所有父级）。已存在视为成功。
     */
    public function ensureRemoteDir(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return true;
        }
        if (isset($this->ensuredDirs[$path])) {
            return $this->ensuredDirs[$path];
        }

        $current = '';
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return false;
            }
            $current .= '/' . $part;
            if (isset($this->ensuredDirs[$current])) {
                if (!$this->ensuredDirs[$current]) {
                    return false;
                }
                continue;
            }
            if (!$this->client->mkdir($current)) {
                $this->ensuredDirs[$current] = false;
                return false;
            }
            $this->ensuredDirs[$current] = true;
        }

        $this->ensuredDirs[$path] = true;
        return true;
    }

    /**
     * 书库固定布局：/Apps/Books、.Moon+、Cache、Cover。
     */
    public function ensureLibraryDirs(): bool
    {
        return $this->ensureRemoteDir($this->path)
            && $this->ensureRemoteDir($this->moon)
            && $this->ensureRemoteDir($this->moon . '/Cache')
            && $this->ensureRemoteDir($this->moon . '/Cover');
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
