<?php

declare(strict_types=1);

namespace app\ai\tool;

use app\utils\DoubanSearch;
use nova\framework\core\Instance;
use nova\plugin\ai\tool\CallableTool;
use nova\plugin\ai\tool\ToolInterface;

/**
 * 豆瓣图书工具。
 *
 * 直接复用 app\utils\DoubanSearch（与豆瓣控制器同一套搜索/解析逻辑），
 * 让 AI 按书名/关键词检索豆瓣并拿到作者、出版社、ISBN、评分、简介、标签等信息。
 */
class DoubanTools extends Instance
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
                'search_douban',
                'Search books on Douban by title/keyword and return matched books with author, publisher, year, ISBN, rating, intro and tags.',
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

        $books = DoubanSearch::getInstance()->search(trim($query));
        if (empty($books)) {
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
            '评分' => $book['rating'] ?? '',
            '页数' => $book['pages'] ?? '',
            '丛书' => $book['series'] ?? '',
            '标签' => $tagStr,
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
