<?php

declare(strict_types=1);

namespace app\ai\tool;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use nova\framework\core\Instance;
use nova\plugin\ai\tool\CallableTool;
use nova\plugin\ai\tool\ToolInterface;

/**
 * 图书库工具集（只读）。
 *
 * 封装 BookDao 的只读查询，让 AI 检索本地藏书，而不暴露原始 SQL——
 * 既安全（无写、无任意 SQL），又省去让模型理解 schema 的成本。
 */
class BookTools extends Instance
{
    private const int MAX_LIMIT = 50;

    /**
     * @return array<int, ToolInterface>
     */
    public function tools(): array
    {
        $str = ['type' => 'string'];
        $empty = ['type' => 'object', 'properties' => new \stdClass()];

        return [
            new CallableTool(
                'list_books',
                'Search/list books in the local library. Filters: keyword (name or author), category, tag, series. Returns paginated brief entries.',
                ['type' => 'object', 'properties' => [
                    'keyword' => $str + ['description' => 'Match book name or author.'],
                    'category' => $str + ['description' => 'Filter by category (单值分类).'],
                    'tag' => $str + ['description' => 'Filter by tag (标签).'],
                    'series' => $str + ['description' => 'Filter by series name.'],
                    'page' => ['type' => 'integer', 'description' => 'Page number, default 1.'],
                    'limit' => ['type' => 'integer', 'description' => 'Page size, default 20, max ' . self::MAX_LIMIT . '.'],
                ], 'required' => []],
                $this->listBooks(...)
            ),
            new CallableTool(
                'get_book',
                'Get full details of one book by id or filename.',
                ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Book id.'],
                    'filename' => $str + ['description' => 'Book filename, e.g. "局外人.epub".'],
                ], 'required' => []],
                $this->getBook(...)
            ),
            new CallableTool(
                'list_categories',
                'List all book categories (分类).',
                $empty,
                fn (): string => $this->joinList(BookDao::getInstance()->getCategories())
            ),
            new CallableTool(
                'list_tags',
                'List all book tags (标签).',
                $empty,
                fn (): string => $this->joinList(BookDao::getInstance()->getTags())
            ),
            new CallableTool(
                'list_series',
                'List all book series names.',
                $empty,
                fn (): string => $this->joinList(BookDao::getInstance()->getSeriesNames())
            ),
        ];
    }

    /** @param array<string,mixed> $a */
    private function listBooks(array $a): string
    {
        $page = max(1, $this->int($a, 'page', 1));
        $limit = max(1, min($this->int($a, 'limit', 20), self::MAX_LIMIT));

        // getList 参数语义（DB 字段命名与业务相反）：
        //   $search=关键词, $series=系列, $category=标签字段, $favorite=分类字段
        $res = BookDao::getInstance()->getList(
            $page,
            $limit,
            $this->str($a, 'keyword'),
            $this->str($a, 'series'),
            $this->str($a, 'tag'),
            $this->str($a, 'category'),
            ''
        );

        $list = $res['list'] ?? [];
        if ($list === []) {
            return 'no books found (total=0)';
        }

        $lines = ['total=' . ($res['total'] ?? 0) . ", page={$page}, limit={$limit}"];
        foreach ($list as $book) {
            $lines[] = $this->brief($book);
        }
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $a */
    private function getBook(array $a): string
    {
        $dao = BookDao::getInstance();

        if (($id = $this->int($a, 'id', 0)) > 0) {
            $book = $dao->getById($id);
        } elseif (($filename = $this->str($a, 'filename')) !== '') {
            $book = $dao->getByFileName($filename);
        } else {
            throw new \RuntimeException('provide id or filename');
        }

        return $book ? $this->detail($book) : 'book not found';
    }

    private function brief(BookModel $b): string
    {
        return sprintf(
            '#%d 《%s》 作者:%s 分类:%s 标签:[%s] 系列:%s 文件:%s',
            $b->id,
            $b->bookName,
            $b->author ?: '-',
            $b->getCategoryName() ?: '-',
            implode(',', $b->getTags()),
            $b->series ?: '-',
            $b->filename
        );
    }

    private function detail(BookModel $b): string
    {
        return implode("\n", [
            'id: ' . $b->id,
            '书名: ' . $b->bookName,
            '作者: ' . ($b->author ?: '-'),
            '分类: ' . ($b->getCategoryName() ?: '-'),
            '标签: ' . implode(',', $b->getTags()),
            '系列: ' . ($b->series ?: '-') . ($b->series ? " #{$b->seriesNum}" : ''),
            '评分: ' . $b->rate,
            '文件: ' . $b->filename,
            '已读: ' . ($b->hasFinishedTag() ? '是' : '否'),
            '简介: ' . ($b->description ?: '-'),
        ]);
    }

    /** @param array<int,string> $items */
    private function joinList(array $items): string
    {
        return $items === [] ? '(empty)' : implode("\n", $items);
    }

    /** @param array<string,mixed> $a */
    private function str(array $a, string $key): string
    {
        return isset($a[$key]) && is_string($a[$key]) ? $a[$key] : '';
    }

    /** @param array<string,mixed> $a */
    private function int(array $a, string $key, int $default): int
    {
        $v = $a[$key] ?? null;
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v) && ctype_digit($v)) {
            return (int)$v;
        }
        return $default;
    }
}
