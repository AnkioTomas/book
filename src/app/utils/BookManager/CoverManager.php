<?php

declare(strict_types=1);

namespace app\utils\BookManager;

use app\utils\Douban;
use nova\framework\core\Context;
use nova\framework\core\File;

class CoverManager extends BaseManager
{
    /** 远端没有封面时，多久之内不再重复向 WebDAV 求证（秒）。 */
    private const MISS_TTL = 3600;

    public function deleteCover(string $filename): bool
    {
        $this->dropCache($filename);
        return $this->client->delete($this->remotePath($filename));
    }

    public function uploadCover(string $file, string $filename): bool
    {

        if (str_starts_with($file, "http")) {
            $file = Douban::download($file);
        }

        if (!$this->client->upload($file, $this->remotePath($filename))) {
            return false;
        }
        // 远端封面已经换了，本地缓存必须作废，否则页面永远显示旧图，
        // 或者继续命中此前下载失败留下的空文件。
        $this->dropCache($filename);
        return true;
    }

    /**
     * 不变量：缓存目录里只允许存在合法图片文件。
     *
     * download() 先创建并截断本地文件再发请求，失败时会留下 0 字节文件。旧实现只看文件在不在，
     * 于是这个空文件被当成有效缓存永久命中，封面再也不会恢复。改成以「内容是否为图片」判定命中，
     * 存量被污染的缓存也会在下一次访问时自动重下修复。
     */
    public function getCover(string $filename): string
    {
        $file = $this->cacheFile($filename);
        if ($this->isImageFile($file)) {
            return $file;
        }

        $cache = Context::instance()->cache;
        $missKey = $this->missKey($filename);
        if ($cache->get($missKey)) {
            return '';
        }

        $this->client->download($this->remotePath($filename), $file);
        if ($this->isImageFile($file)) {
            return $file;
        }

        File::del($file);
        // 记住这次落空，否则整页没封面的书每刷新一次就打一轮 WebDAV。
        $cache->set($missKey, true, self::MISS_TTL);
        return '';
    }

    private function dropCache(string $filename): void
    {
        File::del($this->cacheFile($filename));
        Context::instance()->cache->delete($this->missKey($filename));
    }

    private function missKey(string $filename): string
    {
        return 'cover.miss/' . md5($filename);
    }

    private function remotePath(string $filename): string
    {
        return $this->moon . DS . "Cover" . DS . $this->normalizeFilename($filename) . "_2.png";
    }

    private function cacheFile(string $filename): string
    {
        $path = RUNTIME_PATH . DS . "images" . DS;
        File::mkdir($path);
        return $path . md5($filename) . ".png";
    }

    /**
     * 校验文件是否为图片（纯文件头签名判断，无需额外扩展）。
     *
     * 文件不存在是常规输入（缓存未命中就是这个状态），必须先挡掉：
     * 框架的错误处理器注册在 E_ALL 上且不看 error_reporting()，@ 抑制符拦不住它，
     * fopen 一个不存在的路径会直接抛 ErrorException。
     */
    private function isImageFile(string $file): bool
    {
        if (!File::exists($file)) {
            return false;
        }

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
