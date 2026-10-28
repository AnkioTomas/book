<?php

declare(strict_types=1);

namespace app\utils;

use app\database\model\AnnotationModel;

/**
 * 静读天下（Moon+ Reader）.an 注解文件 → AnnotationModel。
 *
 * 格式契约（对照真实 .an，并参考 MIT 的 MoonReader_NoteSync / siyuan-moon-sync）：
 * - 文件体多为 zlib / raw deflate 压缩的 UTF-8 文本
 * - 段分隔：单独一行 `#`
 * - 每段前 10 行：id, title, fullPath, lowerPath, chapter, 0, position, length, color, timestamp(ms)
 * - 其后 1～2 行正文：有笔记时先笔记后高亮；仅一行则是高亮
 * - 正文后的裸数字是尾部元数据，丢弃
 *
 * device_id 固定为 {@see DEVICE}，与 KOReader 快照互不覆盖。
 */
class MoonReaderAnnotation
{
    public const DEVICE = 'moonreader';

    /**
     * @return AnnotationModel[]
     */
    public static function parseToModels(string $binary, string $bookFilename): array
    {
        $text = self::decode($binary);
        if ($text === '') {
            return [];
        }

        $models = [];
        foreach (self::parseNotes($text) as $note) {
            $model = self::toModel($note, $bookFilename);
            if ($model !== null) {
                $models[] = $model;
            }
        }
        return $models;
    }

    /** 解压 .an 二进制为 UTF-8 文本；失败则当明文再试。 */
    public static function decode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $looksZlib = strlen($binary) > 1 && ord($binary[0]) === 0x78;
        $order = $looksZlib
            ? ['gzuncompress', 'gzinflate', 'gzdecode']
            : ['gzinflate', 'gzuncompress', 'gzdecode'];

        foreach ($order as $fn) {
            if (!function_exists($fn)) {
                continue;
            }
            $out = @$fn($binary);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }

        return self::looksLikeAnText($binary) ? $binary : '';
    }

    /**
     * @return list<array{
     *   id:string,chapter:int,start:int,length:int,color:string,
     *   timestamp:int,note:string,text:string
     * }>
     */
    public static function parseNotes(string $text): array
    {
        $sections = preg_split("/\n#\r?\n/", $text);
        if ($sections === false || count($sections) < 2) {
            return [];
        }

        $notes = [];
        for ($i = 1, $n = count($sections); $i < $n; $i++) {
            $section = trim($sections[$i]);
            if ($section === '') {
                continue;
            }
            $lines = array_map(
                static fn (string $l): string => rtrim($l, "\r"),
                explode("\n", $section)
            );
            if (count($lines) < 10) {
                continue;
            }

            $ts = (int)$lines[9];
            $start = (int)$lines[6];
            $length = (int)$lines[7];
            $id = trim($lines[0]);
            if ($id === '') {
                continue;
            }

            $p = 10;
            while ($p < count($lines) && self::isBlank($lines[$p])) {
                $p++;
            }

            $content = [];
            while (count($content) < 2 && $p < count($lines)) {
                $line = trim($lines[$p]);
                if (self::isBlank($line) || self::isBareNumber($line)) {
                    break;
                }
                $content[] = $line;
                $p++;
            }

            $userNote = '';
            $highlight = '';
            if (count($content) >= 2) {
                $userNote = self::normalizeBody($content[0]);
                $highlight = self::normalizeBody(implode("\n", array_slice($content, 1)));
            } elseif (count($content) === 1) {
                $highlight = self::normalizeBody($content[0]);
            }

            if ($userNote === '' && $highlight === '') {
                continue;
            }

            $notes[] = [
                'id' => $id,
                'chapter' => (int)$lines[4],
                'start' => $start,
                'length' => $length,
                'color' => self::colorToCss(trim($lines[8])),
                'timestamp' => $ts,
                'note' => $userNote,
                'text' => $highlight,
            ];
        }

        return $notes;
    }

    /**
     * @param array{
     *   id:string,chapter:int,start:int,length:int,color:string,
     *   timestamp:int,note:string,text:string
     * } $note
     */
    public static function toModel(array $note, string $bookFilename): ?AnnotationModel
    {
        if ($bookFilename === '' || $note['id'] === '') {
            return null;
        }

        $datetime = self::formatDatetime($note['timestamp']);
        if ($datetime === '') {
            return null;
        }

        $model = new AnnotationModel();
        $model->book_filename = $bookFilename;
        $model->device_id = self::DEVICE;
        $model->text = $note['text'];
        $model->note = $note['note'];
        $model->drawer = '';
        $model->color = $note['color'];
        $model->chapter = $note['chapter'] > 0 ? ('第 ' . $note['chapter'] . ' 章') : '';
        $model->pageno = $note['start'] > 0 ? (int)floor($note['start'] / 1000) : 0;
        $model->page_ref = $note['id'];
        $model->total_pages = 0;
        $model->pos0 = (string)$note['start'];
        $model->pos1 = (string)($note['start'] + max(0, $note['length']));
        $model->datetime = $datetime;
        $model->datetime_updated = $datetime;
        $model->annotation_type = ($note['note'] !== '' && $note['text'] !== '')
            ? 'note'
            : 'highlight';

        return $model;
    }

    /** Moon+ ARGB 有符号整型 → #RRGGBB；已是颜色名/hex 则原样。 */
    public static function colorToCss(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $raw) === 1) {
            return strlen($raw) === 9 ? ('#' . substr($raw, 3)) : $raw;
        }
        if (!preg_match('/^-?\d+$/', $raw)) {
            return $raw;
        }
        $n = (int)$raw;
        $rgb = $n & 0xFFFFFF;
        return sprintf('#%06X', $rgb);
    }

    private static function formatDatetime(int $timestampMs): string
    {
        if ($timestampMs <= 0) {
            return '';
        }
        // Moon+ 时间戳是毫秒；偶发秒级则放过。
        $sec = $timestampMs > 1_000_000_000_000
            ? (int)floor($timestampMs / 1000)
            : $timestampMs;
        return date('Y-m-d H:i:s', $sec);
    }

    private static function normalizeBody(string $s): string
    {
        return trim(preg_replace('/<BR>/i', "\n", $s) ?? $s);
    }

    private static function isBlank(string $l): bool
    {
        return trim($l) === '';
    }

    private static function isBareNumber(string $l): bool
    {
        return preg_match('/^-?\d+$/', trim($l)) === 1;
    }

    private static function looksLikeAnText(string $s): bool
    {
        return str_contains($s, "\n#\n") || str_contains($s, "\n#\r\n");
    }
}
