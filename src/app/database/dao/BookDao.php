<?php

declare(strict_types=1);

namespace app\database\dao;

use app\database\model\BookModel;
use app\task\SyncTask;
use nova\plugin\corn\schedule\TaskerManager;
use nova\plugin\corn\schedule\TaskerTime;
use nova\plugin\orm\object\Dao;
use nova\plugin\orm\object\Field;

use function nova\plugin\task\go;

class BookDao extends Dao
{
    /**
     * 分页查询书籍列表，支持搜索和筛选
     * @param  int    $page     页码
     * @param  int    $limit    每页数量
     * @param  string $search   搜索关键词（书名、作者）
     * @param  string $series   系列筛选
     * @param  string $category 标签筛选（category 字段）
     * @param  string $favorite 分类筛选（favorite 字段）
     * @param  string $finished 已读筛选：1=已读，0=未读
     * @param  string $author   作者筛选（精确匹配）
     * @return array  ['total' => int, 'list' => BookModel[]]
     */
    public function getList(int $page = 1, int $limit = 20, string $search = '', string $series = '', string $category = '', string $favorite = '', string $finished = '', string $author = ''): array
    {
        $where = [];

        $orderBy = "addTime";
        // 搜索：书名或作者
        if (!empty($search)) {
            $where[] = "(bookName LIKE '%:search%' OR author LIKE '%:search%')";
            $where[':search'] = $search;
        }

        // 筛选：系列
        if (!empty($series)) {
            $where['series'] = $series;
            $orderBy = "seriesNum";
        }

        // 筛选：标签（模糊匹配）
        if (!empty($category)) {
            $where[] = "category LIKE '%:category%'";
            $where[':category'] = $category;
        }

        // 筛选：分类
        if (!empty($favorite)) {
            if ($favorite === 'empty') {
                $favorite = '';
            }
            $where['favorite'] = $favorite;
        }

        // 筛选：作者
        if ($author !== '') {
            $where['author'] = $author;
        }

        // 筛选：已读（标签行「已读」）
        if ($finished === '1') {
            $where[] = "CONCAT(CHAR(10), IFNULL(category, ''), CHAR(10)) LIKE CONCAT('%', CHAR(10), '已读', CHAR(10), '%')";
        } elseif ($finished === '0') {
            $where[] = "NOT (CONCAT(CHAR(10), IFNULL(category, ''), CHAR(10)) LIKE CONCAT('%', CHAR(10), '已读', CHAR(10), '%'))";
        }

        $result = $this->getAll([], $where, $page, $limit, $orderBy);

        return [
            'total' => $result['total'],
            'list' => $result['data']
        ];
    }

    /**
     * 根据ID获取书籍
     */
    public function getById(int $id): ?BookModel
    {
        return $this->find(null, ['id' => $id]);
    }

    /**
     * 按 id 批量查询，顺序与入参一致；不存在的 id 跳过。
     *
     * @param  int[]       $ids
     * @return BookModel[]
     */
    public function getByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $rows = $this->select()
            ->where(['id in (:ids)', ':ids' => implode(',', $ids)])
            ->commit();

        $byId = [];
        foreach ($rows as $book) {
            $byId[(int)$book->id] = $book;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    /**
     * 按 $addTimes 批量查询，减少循环中的 N+1 数据库请求。
     *
     * @param  int[]       $addTimes
     * @return BookModel[]
     */
    public function getByAddTime(array $addTimes): array
    {
        $in = implode(',', $addTimes);
        return $this->select()
            ->where(['addTime in (:addTime)', ':addTime' => $in])
            ->commit();
    }

    /**
     * 删除书籍
     */
    public function deleteById(int $id): bool
    {
        $this->delete()->where(['id' => $id])->commit();
        return true;
    }

    /**
     * 获取所有系列名称（去重）
     */
    public function getSeriesNames(): array
    {
        $result = $this->select('series')
                       ->where(['series <> ""'])
                       ->groupBy('series')
                       ->commit(object: false);

        // GROUP BY已经保证唯一性，直接提取列值
        return array_column($result, 'series');
    }

    /**
     * 获取所有标签（去重）。「已读」固定排在第一位，其余按原出现顺序。
     */
    public function getTags(): array
    {
        $result = $this->select(new Field('category'))
                       ->where(['category != ""'])
                       ->commit(object: false);

        // category 可能包含多个标签，需要拆分
        $tags = [];
        $hasFinished = false;
        foreach ($result as $row) {
            $parts = preg_split('/[\n\s]+/', trim($row['category']));
            foreach ($parts as $part) {
                $clean = trim($part);
                if ($clean === '') {
                    continue;
                }
                if ($clean === BookModel::TAG_FINISHED) {
                    $hasFinished = true;
                    continue;
                }
                $tags[$clean] = true;
            }
        }
        $list = array_keys($tags);
        if ($hasFinished) {
            array_unshift($list, BookModel::TAG_FINISHED);
        }
        return $list;
    }

    /**
     * 获取所有分类（去重）
     */
    public function getCategories(): array
    {
        $result = $this->select('favorite')
                       ->where(['favorite <> ""'])
                       ->groupBy('favorite')
                       ->commit(object: false);

        // GROUP BY已经保证唯一性，直接提取列值
        return array_column($result, 'favorite');
    }

    /**
     * 获取所有作者（去重）
     */
    public function getAuthors(): array
    {
        $result = $this->select('author')
                       ->where(['author <> ""'])
                       ->groupBy('author')
                       ->commit(object: false);

        return array_column($result, 'author');
    }

    public function getByFileName(string $filename): ?BookModel
    {
        return $this->find(null, ['filename' => $filename]);
    }

    /**
     * @param  string[]    $filenames
     * @return BookModel[]
     */
    public function getByFilenames(array $filenames): array
    {
        $filenames = array_values(array_unique(array_filter($filenames, static fn ($f) => $f !== '')));
        if ($filenames === []) {
            return [];
        }
        return $this->select()
            ->where(['filename in (:in)', ':in' => implode(',', $filenames)])
            ->commit();
    }

    /**
     * 进度达到 99% 时自动打上「已读」。已有标签则不动。
     *
     * @param  float          $percent 0–100 刻度（与 ReadingProgressModel::$percent 一致）
     * @param  BookModel|null $book    已加载的书籍，避免重复查询
     * @return BookModel|null 实际写入后的书籍；未变更返回 null
     */
    public function markFinishedByProgress(string $filename, float $percent, ?BookModel $book = null): ?BookModel
    {
        if ($filename === '' || $percent < 99.0) {
            return null;
        }
        $book ??= $this->getByFileName($filename);
        if ($book === null || $book->hasFinishedTag()) {
            return null;
        }
        $book->markFinished(true);
        $book->update_at = time() * 1000;
        if (!$this->updateModel($book)) {
            return null;
        }
        return $book;
    }

    public function syncBooks($force = false): void
    {
        TaskerManager::del("syncBooks");
        if ($force) {
            go("书库同步", function () {
                (new SyncTask())->onStart();
            }, 600);
        } else {
            TaskerManager::add(TaskerTime::after(300), new SyncTask(), "syncBooks");
        }

    }
}
