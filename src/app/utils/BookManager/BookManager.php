<?php

declare(strict_types=1);

namespace app\utils\BookManager;

use nova\framework\core\File;
use nova\framework\core\Logger;
use nova\framework\json\Json;

class BookManager extends BaseManager
{
    public function updateBookList(array $books): bool
    {
        if (!$this->ensureRemoteDir($this->moon)) {
            return false;
        }
        $path = $this->moon . "/books.sync";
        $sync = $this->runtime . "books.sync";
        $data = zlib_encode(Json::encode($books), ZLIB_ENCODING_DEFLATE);
        File::write($sync, $data);
        return $this->client->upload($sync, $path);
    }

    public function deleteBook(string $filename): bool
    {
        $path = $this->bookRemotePath($filename);
        if ($path === '') {
            return false;
        }
        return $this->client->delete($path);
    }

    public function uploadBook(string $file, string $filename): bool
    {
        $path = $this->bookRemotePath($filename);
        if ($path === '') {
            return false;
        }
        $dir = dirname($path);
        if ($dir === '' || $dir === '.' || $dir === '/') {
            return false;
        }
        if (!$this->ensureRemoteDir($dir)) {
            return false;
        }
        return $this->client->upload($file, $path);
    }

    public function downloadBook(string $filename, string $localPath): bool
    {
        $path = $this->bookRemotePath($filename);
        if ($path === '') {
            return false;
        }
        File::mkDir(dirname($localPath));
        return $this->client->download($path, $localPath);
    }

    public function bookExists(string $filename): bool
    {
        $path = $this->bookRemotePath($filename);
        if ($path === '') {
            return false;
        }
        try {
            $this->client->getResourceInfo($path);
            return true;
        } catch (\RuntimeException $exception) {
            return false;
        }
    }

    /**
     * 移动/重命名远端书文件（含子目录）。目标父目录不存在时先 mkcol。
     */
    public function moveBook(string $from, string $to, bool $overwrite = false): bool
    {
        $src = $this->bookRemotePath($from);
        $dst = $this->bookRemotePath($to);
        if ($src === '' || $dst === '' || $src === $dst) {
            return false;
        }
        $dir = dirname($dst);
        if ($dir === '' || $dir === '.' || $dir === '/') {
            return false;
        }
        if (!$this->ensureRemoteDir($dir)) {
            Logger::warning("[BookManager] ensureRemoteDir failed: {$dir}");
            return false;
        }
        return $this->client->move($src, $dst, $overwrite);
    }

    /**
     * 列出远端书库目录下所有电子书相对路径（相对 /Apps/Books）。
     *
     * 递归进入分类子目录，跳过 .Moon+。
     * 返回 null 表示无法获取（网络/认证/超时等），调用方必须据此中止任何删除操作，
     * 绝不能把「无法确认」当成「文件不存在」。返回空数组表示目录确实为空。
     *
     * @return array<string>|null
     */
    public function listRemoteFilenames(): ?array
    {
        if (!$this->ensureRemoteDir($this->path)) {
            return null;
        }
        return $this->listRemoteFilenamesUnder($this->path, '');
    }

    /**
     * @return array<string>|null null = 不可达
     */
    private function listRemoteFilenamesUnder(string $absDir, string $relPrefix): ?array
    {
        try {
            $files = $this->client->listDir($absDir);
        } catch (\Throwable $e) {
            return null;
        }

        $names = [];
        foreach ($files as $f) {
            $name = (string)($f['name'] ?? '');
            if ($name === '' || $name === '.Moon+' || str_starts_with($name, '.')) {
                continue;
            }
            $rel = $relPrefix === '' ? $name : $relPrefix . '/' . $name;
            if (!empty($f['is_dir'])) {
                $childAbs = rtrim($absDir, '/') . '/' . $name;
                $child = $this->listRemoteFilenamesUnder($childAbs, $rel);
                if ($child === null) {
                    return null;
                }
                foreach ($child as $c) {
                    $names[] = $c;
                }
                continue;
            }
            $names[] = $rel;
        }
        return $names;
    }

    /**
     * 下载并解析远端 books.sync 元数据。
     *
     * 返回 null 表示无法获取（下载失败/内容损坏），与「远端确实没有书目」严格区分，
     * 调用方据此跳过元数据比对，绝不能把「无法确认」当成「空」去覆盖。
     * 返回空数组表示远端书目确实为空。
     *
     * @return array<int,array>|null
     */
    public function list(): ?array
    {
        $path = $this->moon . "/books.sync";
        $sync = $this->runtime . "books.sync";
        if (!$this->client->download($path, $sync)) {
            return null;
        }
        $raw = zlib_decode((string)file_get_contents($sync));
        if ($raw === false) {
            return null;
        }
        $decoded = Json::decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * 获取远端 books.sync 文件的最后修改时间（秒）。
     * 用于增量同步：远端书目未变化时跳过下载与比对。
     * 返回 null 表示文件不存在或无法获取。
     */
    public function getBookListMtime(): ?int
    {
        $path = $this->moon . "/books.sync";
        try {
            $info = $this->client->getResourceInfo($path);
        } catch (\Throwable $e) {
            return null;
        }
        $mtime = $info['mtime'] ?? 0;
        return $mtime > 0 ? (int)$mtime : null;
    }

    public function delete(string $filename)
    {
        BookManager::getInstance()->deleteBook($filename);
        CoverManager::getInstance()->deleteCover($filename);
        ProgressManager::getInstance()->deleteProgress($filename);
    }

}
