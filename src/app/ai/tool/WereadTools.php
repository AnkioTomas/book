<?php

declare(strict_types=1);

namespace app\ai\tool;

use app\utils\WereadSearch;
use nova\framework\core\Instance;
use nova\plugin\ai\tool\CallableTool;
use nova\plugin\ai\tool\ToolInterface;

/**
 * 微信读书图书工具。
 *
 * 复用 app\utils\WereadSearch，让 AI 按书名/关键词检索微信读书元数据。
 */
class WereadTools extends Instance
{
    private const int DEFAULT_LIMIT = 5;
    private const int INTRO_MAX = 300;

    /**
     * @return array<int, ToolInterface>
     */
    public function tools(): array
    {
        return [
            new CallableTool(
                'search_weread',
                'Search books on WeChat Reading (微信读书) by title/keyword and return matched books with author, publisher, rating, intro, cover and category tags.',
                ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Book title or keyword to search.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max books to return, default 5.'],
                ], 'required' => ['query']],
                $this->search(...)
            ),
        ];
    }

    /** @param array<string,mixed> $a */
    private function search(array $a): string
    {
        $query = $a['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            throw new \RuntimeException('missing argument: query');
        }

        $limit = (int)($a['limit'] ?? self::DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $books = WereadSearch::getInstance()->search(trim($query), $limit);
        if ($books === []) {
            return "未找到匹配的书籍：{$query}";
        }

        $lines = [];
        foreach (array_slice($books, 0, $limit) as $i => $book) {
            $lines[] = $this->format($i + 1, $book);
        }

        return implode("\n\n", $lines);
    }

    /** @param array<string,mixed> $book */
    private function format(int $no, array $book): string
    {
        $intro = $book['full_intro'] ?? $book['intro'] ?? '';
        if (is_string($intro) && mb_strlen($intro) > self::INTRO_MAX) {
            $intro = mb_substr($intro, 0, self::INTRO_MAX) . '…';
        }

        $tags = $book['tags'] ?? [];
        $tagStr = is_array($tags) ? implode('、', $tags) : (string)$tags;

        $fields = [
            '作者' => $book['author'] ?? '',
            '出版社' => $book['publisher'] ?? '',
            '出版年' => $book['year'] ?? '',
            'ISBN' => $book['isbn'] ?? '',
            '微信读书评分(满分10)' => $book['rating'] ?? '',
            '评分标签' => $book['rating_label'] ?? '',
            '在读人数' => !empty($book['reading_count']) ? (string)$book['reading_count'] : '',
            '标签' => $tagStr,
            '封面' => $book['cover_url'] ?? '',
            '链接' => $book['url'] ?? '',
        ];

        $out = "{$no}. {$book['title']}";
        foreach ($fields as $label => $value) {
            if ($value !== '' && $value !== null) {
                $out .= "\n   {$label}：{$value}";
            }
        }
        if ($intro !== '') {
            $out .= "\n   简介：{$intro}";
        }

        return $out;
    }
}
