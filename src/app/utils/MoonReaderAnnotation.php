<?php

declare(strict_types=1);

namespace app\utils;

use app\database\model\AnnotationModel;

/**
 * 静读天下（Moon+ Reader）.an 注解文件 → AnnotationModel。
 *
 * 格式契约（对照静读天下 v10.7 BookDb.exportNotesToText / deletedNotesToText）：
 * - 文件体多为 zlib / raw deflate 压缩的 UTF-8 文本
 * - 段分隔：单独一行 `#`
 * - 每段严格对应 SQLite `notes` 表 16 列：
 *   [0]_id, [1]book, [2]filename, [3]lowerFilename, [4]lastChapter, [5]lastSplitIndex,
 *   [6]lastPosition, [7]highlightLength, [8]highlightColor, [9]time(ms),
 *   [10]bookmark, [11]note, [12]original, [13]underline, [14]strikethrough, [15]bak
 * - 末尾可能追加删除墓碑段：[15] 为 `*DELETED*`，必须跳过
 *
 * device_id 固定为 {@see DEVICE}，与 KOReader 快照互不覆盖。
 */
class MoonReaderAnnotation
{
    public const DEVICE = 'moonreader';
    private const DELETED_MARKER = '*DELETED*';

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
        $sections = preg_split("/\r?\n#\r?\n/", "\n" . $text);
        if ($sections === false || count($sections) < 2) {
            return [];
        }

        $notes = [];
        for ($i = 1, $n = count($sections); $i < $n; $i++) {
            $section = $sections[$i];
            if (trim($section) === '') {
                continue;
            }
            $lines = array_map(
                static fn (string $l): string => rtrim($l, "\r"),
                explode("\n", $section)
            );
            if (count($lines) < 11) {
                continue;
            }
            if (trim($lines[15] ?? '') === self::DELETED_MARKER) {
                continue;
            }

            $ts = (int)$lines[9];
            $start = (int)$lines[6];
            $length = (int)$lines[7];
            $id = trim($lines[0]);
            if ($id === '') {
                continue;
            }

            $bookmark = self::normalizeBody($lines[10] ?? '');
            $userNote = self::normalizeBody($lines[11] ?? '');
            $highlight = self::normalizeBody($lines[12] ?? '');
            if ($highlight === '' && $bookmark !== '') {
                $highlight = $bookmark;
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

    private static function looksLikeAnText(string $s): bool
    {
        return str_starts_with($s, "#\n")
            || str_starts_with($s, "#\r\n")
            || str_contains($s, "\n#\n")
            || str_contains($s, "\n#\r\n");
    }
}
