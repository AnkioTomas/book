<?php

declare(strict_types=1);

namespace app\utils\BookOrganizer;

use nova\framework\core\Logger;
use Throwable;
use ZipArchive;

/**
 * 纯 PHP 读取电子书元数据与封面。
 * 支持 EPUB、MOBI/AZW/AZW3；其他格式返回空结果。
 */
class EbookMeta
{
    private const UNKNOWN_AUTHORS = ['unknown', '未知', 'anonymous'];

    /**
     * @return array{title?:string, author?:string, publisher?:string, description?:string, language?:string, isbn?:string, year?:string}
     */
    public static function read(string $bookPath, string $filename = ''): array
    {
        try {
            return match (self::format($bookPath, $filename)) {
                'epub' => self::epubMeta($bookPath),
                'mobi' => self::mobiMeta($bookPath),
                default => [],
            };
        } catch (Throwable $e) {
            Logger::debug('[EbookMeta] 读取元数据失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 提取封面并写入 $savePath，成功返回 true
     */
    public static function extractCover(string $bookPath, string $savePath, string $filename = ''): bool
    {
        try {
            $data = match (self::format($bookPath, $filename)) {
                'epub' => self::epubCover($bookPath),
                'mobi' => self::mobiCover($bookPath),
                default => '',
            };
        } catch (Throwable $e) {
            Logger::debug('[EbookMeta] 提取封面失败: ' . $e->getMessage());
            return false;
        }
        if ($data === '' || !self::isImage($data)) {
            return false;
        }
        return file_put_contents($savePath, $data) !== false;
    }

    private static function format(string $bookPath, string $filename): string
    {
        if (!is_file($bookPath)) {
            return '';
        }
        $ext = strtolower(pathinfo($filename !== '' ? $filename : $bookPath, PATHINFO_EXTENSION));
        $fp = fopen($bookPath, 'rb');
        if (!$fp) {
            return '';
        }
        $head = (string)fread($fp, 68);
        fclose($fp);

        if (str_starts_with($head, 'PK') && ($ext === 'epub' || $ext === '')) {
            return 'epub';
        }
        if (in_array(substr($head, 60, 8), ['BOOKMOBI', 'TEXtREAd'], true) && $ext !== 'prc') {
            return 'mobi';
        }
        return '';
    }

    private static function isImage(string $data): bool
    {
        return str_starts_with($data, "\xFF\xD8\xFF")
            || str_starts_with($data, "\x89PNG")
            || str_starts_with($data, 'GIF8')
            || (str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP');
    }

    // ------------------------------------------------------------------ EPUB

    /**
     * @return array{0: ZipArchive, 1: string, 2: string}|null [zip, opf 路径, opf 内容]
     */
    private static function openOpf(string $bookPath): ?array
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($bookPath) !== true) {
            return null;
        }
        $container = $zip->getFromName('META-INF/container.xml');
        if ($container === false || !preg_match('/full-path\s*=\s*["\']([^"\']+)["\']/i', $container, $m)) {
            $zip->close();
            return null;
        }
        $opfPath = urldecode($m[1]);
        $opf = $zip->getFromName($opfPath);
        if ($opf === false) {
            $zip->close();
            return null;
        }
        return [$zip, $opfPath, $opf];
    }

    private static function epubMeta(string $bookPath): array
    {
        $opened = self::openOpf($bookPath);
        if ($opened === null) {
            return [];
        }
        [$zip, , $opf] = $opened;
        $zip->close();

        $out = [];
        $set = static function (string $key, ?string $value) use (&$out): void {
            $value = self::clean($value);
            if ($value !== '') {
                $out[$key] = $value;
            }
        };

        $set('title', self::dc($opf, 'title'));
        $author = self::clean(self::dc($opf, 'creator'));
        if ($author !== '' && !in_array(mb_strtolower($author), self::UNKNOWN_AUTHORS, true)) {
            $out['author'] = $author;
        }
        $set('publisher', self::dc($opf, 'publisher'));
        $set('description', self::dc($opf, 'description'));
        $set('language', self::dc($opf, 'language'));

        $date = self::clean(self::dc($opf, 'date'));
        if (preg_match('/^(\d{4})/', $date, $m)) {
            $out['year'] = $m[1];
        }

        if (preg_match_all('/<dc:identifier[^>]*>(.*?)<\/dc:identifier>/is', $opf, $ids)) {
            foreach ($ids[1] as $id) {
                $digits = preg_replace('/[^0-9Xx]/', '', self::clean($id));
                if (preg_match('/^(97[89]\d{10}|\d{9}[\dXx])$/', (string)$digits)) {
                    $out['isbn'] = (string)$digits;
                    break;
                }
            }
        }
        return $out;
    }

    private static function dc(string $opf, string $tag): ?string
    {
        if (preg_match('/<dc:' . $tag . '(?:\s[^>]*)?>(.*?)<\/dc:' . $tag . '>/is', $opf, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function clean(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = preg_replace('/<!\[CDATA\[(.*?)\]\]>/s', '$1', $value);
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_XML1, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function epubCover(string $bookPath): string
    {
        $opened = self::openOpf($bookPath);
        if ($opened === null) {
            return '';
        }
        [$zip, $opfPath, $opf] = $opened;
        try {
            $items = [];
            if (preg_match_all('/<item\b[^>]*>/i', $opf, $tags)) {
                foreach ($tags[0] as $tag) {
                    $items[] = [
                        'id' => self::attr($tag, 'id'),
                        'href' => self::attr($tag, 'href'),
                        'type' => self::attr($tag, 'media-type'),
                        'props' => self::attr($tag, 'properties'),
                    ];
                }
            }

            $href = '';
            // EPUB3：properties="cover-image"
            foreach ($items as $item) {
                if (in_array('cover-image', preg_split('/\s+/', $item['props']) ?: [], true)) {
                    $href = $item['href'];
                    break;
                }
            }
            // EPUB2：<meta name="cover" content="id">
            if ($href === '' && preg_match_all('/<meta\b[^>]*>/i', $opf, $metas)) {
                foreach ($metas[0] as $meta) {
                    if (strtolower(self::attr($meta, 'name')) === 'cover') {
                        $coverId = self::attr($meta, 'content');
                        foreach ($items as $item) {
                            if ($item['id'] === $coverId) {
                                $href = $item['href'];
                                break 2;
                            }
                        }
                    }
                }
            }
            // 兜底：名字里带 cover 的图片
            if ($href === '') {
                foreach ($items as $item) {
                    if (str_starts_with($item['type'], 'image/') && stripos($item['id'] . $item['href'], 'cover') !== false) {
                        $href = $item['href'];
                        break;
                    }
                }
            }
            if ($href === '') {
                return '';
            }

            $data = $zip->getFromName(self::resolve($opfPath, $href));
            return $data === false ? '' : $data;
        } finally {
            $zip->close();
        }
    }

    private static function attr(string $tag, string $name): string
    {
        if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $m)) {
            return html_entity_decode($m[1] !== '' ? $m[1] : ($m[2] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return '';
    }

    /** 相对 OPF 所在目录解析 href，并处理 ../ 与 URL 编码 */
    private static function resolve(string $opfPath, string $href): string
    {
        $href = urldecode(explode('#', $href)[0]);
        $dir = dirname($opfPath);
        $parts = $dir === '.' ? [] : explode('/', $dir);
        foreach (explode('/', $href) as $seg) {
            if ($seg === '..') {
                array_pop($parts);
            } elseif ($seg !== '.' && $seg !== '') {
                $parts[] = $seg;
            }
        }
        return implode('/', $parts);
    }

    // ------------------------------------------------------------ MOBI / AZW

    /**
     * @return array{offsets: int[], size: int, rec0: string, exth: array<int, string[]>, firstImage: int, encoding: int, fullName: string}|null
     */
    private static function mobiParse(string $bookPath): ?array
    {
        $size = filesize($bookPath);
        if ($size === false || $size < 80) {
            return null;
        }
        $fp = fopen($bookPath, 'rb');
        if (!$fp) {
            return null;
        }
        try {
            $head = (string)fread($fp, 78);
            if (!in_array(substr($head, 60, 8), ['BOOKMOBI', 'TEXtREAd'], true)) {
                return null;
            }
            $count = unpack('n', substr($head, 76, 2))[1];
            $list = (string)fread($fp, $count * 8);
            $offsets = [];
            for ($i = 0; $i < $count; $i++) {
                $offsets[$i] = unpack('N', substr($list, $i * 8, 4))[1];
            }
            if ($count < 2) {
                return null;
            }
            $rec0Len = min($offsets[1], $size) - $offsets[0];
            fseek($fp, $offsets[0]);
            $rec0 = (string)fread($fp, max(0, $rec0Len));
            if (strlen($rec0) < 92 || substr($rec0, 16, 4) !== 'MOBI') {
                return null;
            }

            $headerLen = unpack('N', substr($rec0, 20, 4))[1];
            $encoding = unpack('N', substr($rec0, 28, 4))[1];
            $firstImage = strlen($rec0) >= 112 ? unpack('N', substr($rec0, 108, 4))[1] : 0xFFFFFFFF;
            $exthFlags = strlen($rec0) >= 132 ? unpack('N', substr($rec0, 128, 4))[1] : 0;

            $exth = [];
            $pos = 16 + $headerLen;
            if (($exthFlags & 0x40) && substr($rec0, $pos, 4) === 'EXTH') {
                $n = unpack('N', substr($rec0, $pos + 8, 4))[1];
                $p = $pos + 12;
                for ($i = 0; $i < $n && $p + 8 <= strlen($rec0); $i++) {
                    $type = unpack('N', substr($rec0, $p, 4))[1];
                    $len = unpack('N', substr($rec0, $p + 4, 4))[1];
                    if ($len < 8) {
                        break;
                    }
                    $exth[$type][] = substr($rec0, $p + 8, $len - 8);
                    $p += $len;
                }
            }

            $nameOff = unpack('N', substr($rec0, 84, 4))[1];
            $nameLen = unpack('N', substr($rec0, 88, 4))[1];
            $fullName = substr($rec0, $nameOff, $nameLen);

            return [
                'offsets' => $offsets,
                'size' => $size,
                'rec0' => $rec0,
                'exth' => $exth,
                'firstImage' => $firstImage,
                'encoding' => $encoding,
                'fullName' => $fullName,
            ];
        } finally {
            fclose($fp);
        }
    }

    private static function mobiText(string $raw, int $encoding): string
    {
        if ($raw === '') {
            return '';
        }
        if ($encoding !== 65001) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        return self::clean($raw);
    }

    private static function mobiMeta(string $bookPath): array
    {
        $mobi = self::mobiParse($bookPath);
        if ($mobi === null) {
            return [];
        }
        $enc = $mobi['encoding'];
        $first = static fn (int $type): string => self::mobiText($mobi['exth'][$type][0] ?? '', $enc);

        $out = [];
        $title = $first(503) !== '' ? $first(503) : self::mobiText($mobi['fullName'], $enc);
        if ($title !== '') {
            $out['title'] = $title;
        }
        $authors = array_filter(array_map(
            static fn (string $a): string => self::mobiText($a, $enc),
            $mobi['exth'][100] ?? []
        ));
        $author = implode(' & ', $authors);
        if ($author !== '' && !in_array(mb_strtolower($author), self::UNKNOWN_AUTHORS, true)) {
            $out['author'] = $author;
        }
        foreach ([101 => 'publisher', 103 => 'description', 524 => 'language'] as $type => $key) {
            if (($v = $first($type)) !== '') {
                $out[$key] = $v;
            }
        }
        $isbn = preg_replace('/[^0-9Xx]/', '', $first(104));
        if ($isbn !== '') {
            $out['isbn'] = (string)$isbn;
        }
        if (preg_match('/^(\d{4})/', $first(106), $m)) {
            $out['year'] = $m[1];
        }
        return $out;
    }

    private static function mobiCover(string $bookPath): string
    {
        $mobi = self::mobiParse($bookPath);
        if ($mobi === null || $mobi['firstImage'] === 0xFFFFFFFF) {
            return '';
        }
        // 201 封面偏移，缺失时退回 202 缩略图
        $raw = $mobi['exth'][201][0] ?? ($mobi['exth'][202][0] ?? '');
        if (strlen($raw) !== 4) {
            return '';
        }
        $index = $mobi['firstImage'] + unpack('N', $raw)[1];
        $offsets = $mobi['offsets'];
        if (!isset($offsets[$index])) {
            return '';
        }
        $end = $offsets[$index + 1] ?? $mobi['size'];
        $fp = fopen($bookPath, 'rb');
        if (!$fp) {
            return '';
        }
        fseek($fp, $offsets[$index]);
        $data = (string)fread($fp, max(0, $end - $offsets[$index]));
        fclose($fp);
        return $data;
    }
}
