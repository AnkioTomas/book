<?php

namespace app\database\dao;

use app\database\model\BookModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookManager\ProgressManager;
use app\utils\SyncTask;
use nova\plugin\corn\schedule\TaskerManager;
use nova\plugin\corn\schedule\TaskerTime;
use nova\plugin\orm\object\Dao;
use nova\plugin\orm\object\Field;
use function nova\framework\dump;

class BookDao extends Dao
{
    /**
     * 分页查询书籍列表，支持搜索和筛选
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $search 搜索关键词（书名、作者）
     * @param string $series 系列筛选
     * @param string $category 分类筛选
     * @param string $favorite 收藏筛选
     * @param string $finished 已读完筛选
     * @return array ['total' => int, 'list' => BookModel[]]
     */
    public function getList(int $page = 1, int $limit = 20, string $search = '', string $series = '', string $category = '', string $favorite = '', string $finished = ''): array
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
        
        // 筛选：分类（模糊匹配）
        if (!empty($category)) {
            $where[] = "category LIKE '%:category%'";
            $where[':category'] = $category;
        }
        
        // 筛选：收藏
        if (!empty($favorite)) {
            $where['favorite'] = $favorite;
        }

        // 筛选：是否已读完
        if ($finished !== '') {
            $where['isFinished'] = ((int)$finished) > 0 ? 1 : 0;
        }
        
        $result = $this->getAll([], $where, $page, $limit, true, $orderBy);
        
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
     * 按 $addTimes 批量查询，减少循环中的 N+1 数据库请求。
     *
     * @param int[] $addTimes
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
     * 获取所有分类（去重）
     */
    public function getCategories(): array
    {
        $result = $this->select(new Field('category'))
                       ->where(['category != ""'])
                       ->commit(object: false);
        
        // category可能包含多个分类，需要拆分
        // 使用关联数组去重，O(1)复杂度
        $categories = [];
        foreach ($result as $row) {
            $parts = preg_split('/[\n\s]+/', trim($row['category']));
            foreach ($parts as $part) {
                $clean = trim($part);
                if ($clean !== '') {
                    $categories[$clean] = true;
                }
            }
        }
        return array_keys($categories);
    }
    
    /**
     * 获取所有收藏夹标签（去重）
     */
    public function getFavorites(): array
    {
        $result = $this->select('favorite')
                       ->where(['favorite <> ""'])
                       ->groupBy('favorite')
                       ->commit(object: false);
        
        // GROUP BY已经保证唯一性，直接提取列值
        return array_column($result, 'favorite');
    }

    public function getByFileName(string $filename): ?BookModel
    {
        return $this->find(null, ['filename' => $filename]);
    }

    public function syncBooks($force = false): void
    {
        TaskerManager::del("syncBooks");
        if ($force){
            (new SyncTask())->onStart();
        }else{
            TaskerManager::add(TaskerTime::after(300),new SyncTask(),"syncBooks");
        }

    }
}