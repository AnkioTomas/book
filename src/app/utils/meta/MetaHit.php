<?php

declare(strict_types=1);

namespace app\utils\meta;

/**
 * 元数据搜索统一命中行（对齐 DoubanSearch / 编辑表单可写字段）。
 */
final class MetaHit
{
    /**
     * @param  array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function normalize(array $row, string $source): ?array
    {
        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $tags = $row['tags'] ?? [];
        if (is_string($tags)) {
            $tags = preg_split('/[\n,，、]+/u', $tags) ?: [];
        }
        if (!is_array($tags)) {
            $tags = [];
        }
        $cleanTags = [];
        foreach ($tags as $t) {
            $t = trim((string)$t);
            if ($t !== '' && !in_array($t, $cleanTags, true)) {
                $cleanTags[] = $t;
            }
        }

        $rating = $row['rating'] ?? '';
        if (is_numeric($rating)) {
            $rating = (float)$rating;
            // 已经是 0–5 的留给前端；>5 视为十分制
        } else {
            $rating = '';
        }

        $intro = trim((string)($row['intro'] ?? $row['description'] ?? ''));
        $full = trim((string)($row['full_intro'] ?? ''));
        if ($full === '') {
            $full = $intro;
        }

        return [
            'title' => $title,
            'author' => trim((string)($row['author'] ?? $row['authors'] ?? '')),
            'publisher' => trim((string)($row['publisher'] ?? '')),
            'year' => trim((string)($row['year'] ?? '')),
            'isbn' => trim((string)($row['isbn'] ?? '')),
            'rating' => $rating,
            'intro' => $intro,
            'full_intro' => $full,
            'tags' => $cleanTags,
            'cover_url' => trim((string)($row['cover_url'] ?? $row['cover'] ?? '')),
            'url' => trim((string)($row['url'] ?? '')),
            'series' => trim((string)($row['series'] ?? '')),
            'pages' => trim((string)($row['pages'] ?? '')),
            'price' => trim((string)($row['price'] ?? '')),
            'source' => $source,
        ];
    }

    /**
     * @param  list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function mapList(array $rows, string $source): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hit = self::normalize($row, $source);
            if ($hit !== null) {
                $out[] = $hit;
            }
        }
        return $out;
    }
}
