<?php

declare(strict_types=1);

namespace app\ai\tool;

use app\utils\meta\MetaRegistry;
use nova\framework\core\Instance;
use nova\plugin\ai\tool\CallableTool;
use nova\plugin\ai\tool\ToolInterface;

/**
 * 元数据检索工具：统一走 MetaRegistry，禁止 AI 自己 curl/fetch 页面。
 */
class MetaTools extends Instance
{
    private const int DEFAULT_LIMIT = 5;
    private const int INTRO_MAX = 300;

    /**
     * @return array<int, ToolInterface>
     */
    public function tools(): array
    {
        $sourceHint = [];
        foreach (MetaRegistry::getInstance()->catalog() as $src) {
            $sourceHint[] = $src['id'] . '(' . $src['name'] . ')';
        }
        $hint = implode(', ', $sourceHint);

        return [
            new CallableTool(
                'search_meta',
                'Search book metadata from a catalog source and return matched books (title, author, rating, intro, tags, cover, url). '
                . 'Available source ids: ' . $hint . '. '
                . 'Do not fetch book pages yourself; call this tool instead.',
                ['type' => 'object', 'properties' => [
                    'source' => [
                        'type' => 'string',
                        'description' => 'Catalog source id. One of: ' . $hint,
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Book title or keyword to search.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max books to return, default 5.',
                    ],
                ], 'required' => ['source', 'query']],
                $this->search(...)
            ),
        ];
    }

    /** @param array<string,mixed> $a */
    private function search(array $a): string
    {
        $source = $a['source'] ?? null;
        $query = $a['query'] ?? null;
        if (!is_string($source) || trim($source) === '') {
            throw new \RuntimeException('missing argument: source');
        }
        if (!is_string($query) || trim($query) === '') {
            throw new \RuntimeException('missing argument: query');
        }

        $source = trim($source);
        $query = trim($query);
        $limit = (int)($a['limit'] ?? self::DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        try {
            $books = MetaRegistry::getInstance()->search($source, $query);
        } catch (\InvalidArgumentException $e) {
            $ids = array_column(MetaRegistry::getInstance()->catalog(), 'id');
            return '未知数据源: ' . $source . '。可用: ' . implode(', ', $ids);
        } catch (\Throwable $e) {
            return '检索失败(' . $source . '): ' . $e->getMessage();
        }

        if ($books === []) {
            return "未找到匹配的书籍：[{$source}] {$query}";
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

        $rating = $book['rating'] ?? '';
        $ratingLabel = $rating === '' || $rating === null
            ? ''
            : (is_numeric($rating) && (float)$rating > 5
                ? (string)$rating . '(满分10)'
                : (string)$rating . '(满分5)');

        $fields = [
            '来源' => $book['source'] ?? '',
            '作者' => $book['author'] ?? '',
            '出版社' => $book['publisher'] ?? '',
            '出版年' => $book['year'] ?? '',
            'ISBN' => $book['isbn'] ?? '',
            '评分' => $ratingLabel,
            '页数' => $book['pages'] ?? '',
            '丛书' => $book['series'] ?? '',
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
