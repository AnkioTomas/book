<?php

declare(strict_types=1);

namespace app\utils\BookOrganizer;

use app\database\model\BookModel;

/**
 * 纯函数：根据书籍元数据算出目标相对路径（相对 /Apps/Books）。
 *
 * 规则：
 * - 有系列：{分类}/{作者} - {系列}：{书名}.{ext}
 * - 无系列：{分类}/{作者} - {书名}.{ext}
 */
class Namer
{
    public const DEFAULT_CATEGORY = '未分类';
    public const DEFAULT_AUTHOR = '未知作者';

    /**
     * 算出理想目标相对路径（不含冲突消解后缀）。
     */
    public static function targetPath(BookModel $book): string
    {
        $ext = strtolower(pathinfo($book->filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'epub';
        }

        $category = self::sanitizeSegment($book->getCategoryName());
        if ($category === '') {
            $category = self::DEFAULT_CATEGORY;
        }

        $author = self::sanitizeSegment($book->author);
        if ($author === '') {
            $author = self::DEFAULT_AUTHOR;
        }

        $title = self::sanitizeSegment($book->bookName);
        if ($title === '') {
            $stem = pathinfo($book->filename, PATHINFO_FILENAME);
            // filename 可能是「分类/旧名」
            $title = self::sanitizeSegment(basename((string)$stem));
            if ($title === '') {
                $title = '未命名';
            }
        }

        $series = self::sanitizeSegment($book->series);
        if ($series !== '') {
            // 系列与书名之间用全角冒号，避免 Windows / 部分 WebDAV 禁半角 :
            $base = $author . ' - ' . $series . '：' . $title;
        } else {
            $base = $author . ' - ' . $title;
        }

        return $category . '/' . $base . '.' . $ext;
    }

    /**
     * 在已占用集合上消解冲突：同路径追加 " (2)"、" (3)"…
     *
     * @param array<string,true> $taken 已占用相对路径集合（含自身旧路径时调用方应先排除）
     */
    public static function resolveConflict(string $desired, array $taken): string
    {
        if (!isset($taken[$desired])) {
            return $desired;
        }
        $dir = dirname($desired);
        $ext = pathinfo($desired, PATHINFO_EXTENSION);
        $stem = pathinfo($desired, PATHINFO_FILENAME);
        $prefix = ($dir === '.' || $dir === '') ? '' : $dir . '/';
        $n = 2;
        while (true) {
            $candidate = $prefix . $stem . ' (' . $n . ').' . $ext;
            if (!isset($taken[$candidate])) {
                return $candidate;
            }
            $n++;
            if ($n > 9999) {
                return $prefix . $stem . ' (' . time() . ').' . $ext;
            }
        }
    }

    /**
     * 清洗路径段：非法/易冲突标点与空白统一成下划线。
     *
     * 例：作者 "Smith, John" → "Smith_John"；"张三（译）" → "张三_译"。
     * 系列分隔符用全角「：」在拼接时另行加入，段内半角 : 一律替换。
     */
    public static function sanitizeSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // 路径/Windows 非法 + 控制字符 → _
        $value = preg_replace('/[\/\\\\:\*\?"<>|\x00-\x1F]/u', '_', $value) ?? $value;
        // 逗号/分号/引号/括号等易冲突标点 → _
        $value = preg_replace('/[,，;；、。！？!\'"“”‘’（）()\[\]【】{}〈〉《》「」『』#&+=@%~`]/u', '_', $value) ?? $value;
        // 空白 → _，再压成单个 _
        $value = preg_replace('/[\s_]+/u', '_', $value) ?? $value;

        return trim($value, "._-");
    }
}
