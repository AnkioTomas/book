<?php

declare(strict_types=1);

namespace app\utils\BookOrganizer;

use nova\framework\core\Logger;
use Throwable;
use ZipArchive;

/**
 * 纯 PHP 读取电子书元数据与封面。
 * 支持 EPUB、MOBI/AZW/AZW3、PDF；其他格式返回空结果。
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
                'pdf' => self::pdfMeta($bookPath),
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
                'pdf' => self::pdfCover($bookPath),
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
        if (str_starts_with($head, '%PDF')) {
            return 'pdf';
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

    // ------------------------------------------------------------------- PDF

    private const PDF_SCAN_LIMIT = 64 * 1024 * 1024;

    private static function pdfMeta(string $bookPath): array
    {
        $size = filesize($bookPath);
        if ($size === false || $size === 0) {
            return [];
        }
        // Info 字典在文件尾部，XMP 通常在头部附近，都只读两端
        $fp = fopen($bookPath, 'rb');
        if (!$fp) {
            return [];
        }
        $head = (string)fread($fp, 1024 * 1024);
        $tail = '';
        if ($size > 2 * 1024 * 1024) {
            fseek($fp, -1024 * 1024, SEEK_END);
            $tail = (string)fread($fp, 1024 * 1024);
        }
        fclose($fp);
        $data = $head . $tail;

        $out = [];
        $set = static function (string $key, string $value) use (&$out): void {
            $value = trim($value);
            if ($value !== '' && !isset($out[$key])) {
                $out[$key] = $value;
            }
        };

        foreach (['Title' => 'title', 'Author' => 'author', 'Subject' => 'description'] as $field => $key) {
            $set($key, self::pdfString($data, $field));
        }
        if (preg_match('/\/CreationDate\s*\(D:(\d{4})/', $data, $m)) {
            $out['year'] = $m[1];
        }

        // XMP 兜底
        if (!isset($out['title']) && preg_match('/<dc:title>.*?<rdf:li[^>]*>(.*?)<\/rdf:li>/is', $data, $m)) {
            $set('title', self::clean($m[1]));
        }
        if (!isset($out['author']) && preg_match('/<dc:creator>.*?<rdf:li[^>]*>(.*?)<\/rdf:li>/is', $data, $m)) {
            $set('author', self::clean($m[1]));
        }
        if (isset($out['author']) && in_array(mb_strtolower($out['author']), self::UNKNOWN_AUTHORS, true)) {
            unset($out['author']);
        }
        // 很多 PDF 的 Title 只是软件生成的文件名，没有意义
        if (isset($out['title']) && preg_match('/^(untitled|microsoft word - .*|.*\.(docx?|indd|tex|pdf))$/i', $out['title'])) {
            unset($out['title']);
        }
        return $out;
    }

    /** 读取 /Key (literal) 或 /Key <hex>，处理 UTF-16BE / PDFDocEncoding */
    private static function pdfString(string $data, string $key): string
    {
        if (preg_match('/\/' . $key . '\s*<([0-9A-Fa-f\s]+)>/', $data, $m)) {
            $raw = (string)hex2bin(preg_replace('/\s+/', '', $m[1]) . (strlen(preg_replace('/\s+/', '', $m[1])) % 2 ? '0' : ''));
        } elseif (preg_match('/\/' . $key . '\s*\(((?:[^()\\\\]|\\\\.|\((?:[^()\\\\]|\\\\.)*\))*)\)/s', $data, $m)) {
            $raw = preg_replace_callback(
                '/\\\\(?:([0-7]{1,3})|(.))/s',
                static fn (array $e): string => $e[1] !== ''
                    ? chr(octdec($e[1]) & 0xFF)
                    : (['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f"][$e[2]] ?? $e[2]),
                $m[1]
            );
        } else {
            return '';
        }
        if (str_starts_with($raw, "\xFE\xFF")) {
            return self::clean((string)mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE'));
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return self::clean(substr($raw, 3));
        }
        if (mb_check_encoding($raw, 'UTF-8')) {
            return self::clean($raw);
        }
        return self::clean((string)mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'));
    }

    /**
     * 取 PDF 里第一张足够大的内嵌图作为封面（扫描版、出版社 PDF 的封面页基本都是整页图）。
     * 支持 JPEG（DCTDecode）以及 8 位灰度/RGB 的 Flate 图；纯文字 PDF 没有内嵌图，返回空，
     * 由页面用默认封面兜底。
     */
    private static function pdfCover(string $bookPath): string
    {
        $fp = fopen($bookPath, 'rb');
        if (!$fp) {
            return '';
        }
        try {
            $buffer = '';
            $read = 0;
            while (!feof($fp) && $read < self::PDF_SCAN_LIMIT) {
                $chunk = (string)fread($fp, 4 * 1024 * 1024);
                if ($chunk === '') {
                    break;
                }
                $read += strlen($chunk);
                $buffer .= $chunk;

                $pos = 0;
                $incomplete = false;
                while (preg_match('/\/Subtype\s*\/Image\b/', $buffer, $m, PREG_OFFSET_CAPTURE, $pos)) {
                    $at = $m[0][1];
                    $pos = $at + 1;
                    $objAt = strrpos(substr($buffer, 0, $at), 'obj');
                    $streamAt = strpos($buffer, 'stream', $at);
                    if ($objAt === false || $streamAt === false) {
                        $incomplete = true;
                        break;
                    }
                    $dict = substr($buffer, $objAt, $streamAt - $objAt);
                    $start = $streamAt + 6;
                    if (substr($buffer, $start, 2) === "\r\n") {
                        $start += 2;
                    } elseif (($buffer[$start] ?? '') === "\n" || ($buffer[$start] ?? '') === "\r") {
                        $start += 1;
                    }
                    $end = strpos($buffer, 'endstream', $start);
                    if ($end === false) {
                        $incomplete = true;
                        break;
                    }
                    $pos = $end;
                    $image = self::pdfImageData($dict, substr($buffer, $start, $end - $start));
                    if ($image !== '' && self::bigEnough($image)) {
                        return $image;
                    }
                }
                // 保留尾部，避免对象被分块截断；还没读完整的对象留到下一轮
                $keep = $incomplete ? strlen($buffer) : 2 * 1024 * 1024;
                $buffer = substr($buffer, max(0, strlen($buffer) - $keep));
            }
        } finally {
            fclose($fp);
        }
        return '';
    }

    /** 把 PDF 图像流转成 JPEG/PNG 二进制，不支持的编码返回空 */
    private static function pdfImageData(string $dict, string $stream): string
    {
        if (str_contains($dict, 'DCTDecode')) {
            return str_starts_with($stream, "\xFF\xD8") ? $stream : '';
        }
        if (!str_contains($dict, 'FlateDecode') || !function_exists('imagecreatetruecolor')) {
            return '';
        }
        if (!preg_match('/\/Filter\s*\[?\s*\/FlateDecode\s*\]?\s*(?:\/|>>)/', $dict)) {
            return '';
        }
        if (!preg_match('/\/Width\s+(\d+)/', $dict, $w) || !preg_match('/\/Height\s+(\d+)/', $dict, $h)) {
            return '';
        }
        if (!preg_match('/\/BitsPerComponent\s+8\b/', $dict)) {
            return '';
        }
        $width = (int)$w[1];
        $height = (int)$h[1];
        if ($width * $height > 40_000_000 || $width < 1 || $height < 1) {
            return '';
        }
        $comps = str_contains($dict, '/DeviceRGB') ? 3 : (str_contains($dict, '/DeviceGray') ? 1 : 0);
        if ($comps === 0) {
            return '';
        }
        $raw = @gzuncompress(rtrim($stream, "\r\n"));
        if ($raw === false) {
            $raw = @gzuncompress($stream);
        }
        if ($raw === false) {
            return '';
        }

        $rowLen = $width * $comps;
        $predictor = preg_match('/\/Predictor\s+(\d+)/', $dict, $p) ? (int)$p[1] : 1;
        if ($predictor >= 10) {
            $raw = self::pngUnfilter($raw, $rowLen, $height, $comps);
            if ($raw === '') {
                return '';
            }
        } elseif ($predictor !== 1) {
            return '';
        }
        if (strlen($raw) < $rowLen * $height) {
            return '';
        }

        $im = imagecreatetruecolor($width, $height);
        $offset = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($comps === 3) {
                    $color = (ord($raw[$offset]) << 16) | (ord($raw[$offset + 1]) << 8) | ord($raw[$offset + 2]);
                } else {
                    $g = ord($raw[$offset]);
                    $color = ($g << 16) | ($g << 8) | $g;
                }
                imagesetpixel($im, $x, $y, $color);
                $offset += $comps;
            }
        }
        ob_start();
        imagejpeg($im, null, 85);
        $out = (string)ob_get_clean();
        imagedestroy($im);
        return $out;
    }

    /** 还原 PNG predictor（每行首字节为滤波类型） */
    private static function pngUnfilter(string $raw, int $rowLen, int $height, int $bpp): string
    {
        $stride = $rowLen + 1;
        if (strlen($raw) < $stride * $height) {
            return '';
        }
        $out = '';
        $prev = array_fill(0, $rowLen, 0);
        for ($y = 0; $y < $height; $y++) {
            $type = ord($raw[$y * $stride]);
            $line = array_values(unpack('C*', substr($raw, $y * $stride + 1, $rowLen)));
            for ($i = 0; $i < $rowLen; $i++) {
                $a = $i >= $bpp ? $line[$i - $bpp] : 0;
                $b = $prev[$i];
                $c = $i >= $bpp ? $prev[$i - $bpp] : 0;
                $line[$i] = match ($type) {
                    1 => ($line[$i] + $a) & 0xFF,
                    2 => ($line[$i] + $b) & 0xFF,
                    3 => ($line[$i] + intdiv($a + $b, 2)) & 0xFF,
                    4 => ($line[$i] + self::paeth($a, $b, $c)) & 0xFF,
                    default => $line[$i],
                };
            }
            $out .= pack('C*', ...$line);
            $prev = $line;
        }
        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
    }

    /** 过滤 logo、图标等小图：短边至少 200 像素 */
    private static function bigEnough(string $jpeg): bool
    {
        $info = @getimagesizefromstring($jpeg);
        return $info !== false && min($info[0], $info[1]) >= 200;
    }
}
