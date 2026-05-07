<?php

declare(strict_types=1);

namespace app\utils\BookManager;

use app\utils\Douban;
use nova\framework\core\Context;
use nova\framework\core\File;

class CoverManager extends BaseManager
{
    public function deleteCover(string $filename): bool
    {
        $path = $this->moon . DS . "Cover" . DS . $this->normalizeFilename($filename) . "_2.png";
        return $this->client->delete($path);
    }

    public function uploadCover(string $file, string $filename): bool
    {

        if (str_starts_with($file, "http")) {
            $file = Douban::download($file);
        }

        $path = $this->moon . DS . "Cover" . DS . $this->normalizeFilename($filename) . "_2.png";
        return $this->client->upload($file, $path);
    }

    private function listAll(): array
    {
        $cacheName = "cover_list";
        $cache = Context::instance()->cache;

        // 1. 同步检查逻辑：如果是 WebDAV 更新，则主动清理缓存
        if (SyncManager::getInstance()->isWebdavNewerThanDatabase()) {
            $cache->delete($cacheName);
        }

        // 2. 尝试从缓存获取
        $list = $cache->get($cacheName);
        if (is_array($list)) {
            return $list;
        }

        // 3. 获取远程数据
        $dirPath = $this->moon . DS . "Cover";
        $files = $this->client->listDir($dirPath);

        // 4. 处理数据并转换
        // 使用 array_column 替代 array_map，更高效
        $list = $files ? array_column($files, 'name') : [];

        // 5. 写入缓存 (建议设置合理的过期时间，例如 3600 秒)
        $cache->set($cacheName, $list);

        return $list;
    }

    public function getCover(string $filename): string
    {
        $remotePath = $this->moon . DS . "Cover" . DS . $this->normalizeFilename($filename) . "_2.png";
        $key = md5($filename);

        $path = RUNTIME_PATH . DS . "images" . DS;
        File::mkdir($path);
        $file = $path . $key . ".png";
        if (File::exists($file)) {
            return $file;
        }
        if ($this->client->download($remotePath, $file)) {
            // 仅当文件可被识别为图片时才返回，避免把错误页面当成封面缓存。
            if (!$this->isImageFile($file)) {
                File::del($file);
                return '';
            }
            return $file;
        }
        return '';
    }

    /**
     * 校验文件是否为图片（纯文件头签名判断，无需额外扩展）。
     */
    private function isImageFile(string $file): bool
    {

        $fp = @fopen($file, 'rb');
        if ($fp === false) {
            return false;
        }

        $header = (string)fread($fp, 12);
        fclose($fp);

        // JPEG: FF D8 FF
        if (strncmp($header, "\xFF\xD8\xFF", 3) === 0) {
            return true;
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (strncmp($header, "\x89PNG\r\n\x1A\n", 8) === 0) {
            return true;
        }

        // GIF: GIF87a / GIF89a
        if (strncmp($header, 'GIF87a', 6) === 0 || strncmp($header, 'GIF89a', 6) === 0) {
            return true;
        }

        // WebP: RIFF....WEBP
        if (strncmp($header, 'RIFF', 4) === 0 && substr($header, 8, 4) === 'WEBP') {
            return true;
        }

        // BMP: BM
        if (strncmp($header, 'BM', 2) === 0) {
            return true;
        }

        // ICO: 00 00 01 00
        if (strncmp($header, "\x00\x00\x01\x00", 4) === 0) {
            return true;
        }

        // TIFF: II*\0 or MM\0*
        if (strncmp($header, "II\x2A\x00", 4) === 0 || strncmp($header, "MM\x00\x2A", 4) === 0) {
            return true;
        }

        return false;
    }

}
