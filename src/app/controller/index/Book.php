<?php

namespace app\controller\index;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use nova\framework\http\Response;
use nova\framework\json\Json;

class Book extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 获取书籍列表（支持分页、搜索、筛选）
     * GET /book/list?page=1&limit=20&search=xxx&filterType=category&filterValue=xxx
     */
    public function list(): Response
    {
        $page = intval($this->request->get('page', 1));
        $limit = intval($this->request->get('limit', 20));
        $search = trim($this->request->get('search', ''));
        $filterType = trim($this->request->get('filterType', ''));
        $filterValue = trim($this->request->get('filterValue', ''));
        
        $result = BookDao::getInstance()->getList($page, $limit, $search, $filterType, $filterValue);
        
        return Response::asJson([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'list' => $result['list']
            ]
        ]);
    }
    
    /**
     * 获取筛选选项（系列、分类、收藏夹）
     * GET /book/filters
     */
    public function filters(): Response
    {
        return Response::asJson([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'seriesNames' => BookDao::getInstance()->getSeriesNames(),
                'categories' => BookDao::getInstance()->getCategories(),
                'favorites' => BookDao::getInstance()->getFavorites()
            ]
        ]);
    }
    
    /**
     * 获取单本书籍详情
     * GET /book/detail?id=1
     */
    public function detail(): Response
    {
        $id = intval($this->request->get('id', 0));
        
        if ($id <= 0) {
            return Response::asJson(['code' => 400, 'msg' => '参数错误']);
        }
        
        $book = BookDao::getInstance()->getById($id);
        
        if (!$book) {
            return Response::asJson(['code' => 404, 'msg' => '书籍不存在']);
        }
        
        return Response::asJson([
            'code' => 200,
            'msg' => 'success',
            'data' => $book
        ]);
    }
    
    /**
     * 添加书籍
     * POST /book/add
     */
    public function add(): Response
    {
        $data = $this->request->post();
        
        // 验证必填字段
        if (empty($data['bookName'])) {
            return Response::asJson(['code' => 400, 'msg' => '书名不能为空']);
        }
        
        $book = new BookModel($data);
        $book->addTime = time() * 1000;
        
        // 处理groupBooks（如果是JSON字符串，解码为数组）
        if (isset($data['groupBooks']) && is_string($data['groupBooks'])) {
            $book->groupBooks = Json::decode($data['groupBooks'], true) ?: [];
        }
        
        if (BookDao::getInstance()->insertModel($book)) {
            return Response::asJson(['code' => 200, 'msg' => '添加成功']);
        }
        
        return Response::asJson(['code' => 500, 'msg' => '添加失败']);
    }
    
    /**
     * 更新书籍
     * POST /book/update
     */
    public function update(): Response
    {
        $data = $this->request->post();
        $id = intval($data['id'] ?? 0);
        
        if ($id <= 0) {
            return Response::asJson(['code' => 400, 'msg' => '参数错误']);
        }
        
        $book = BookDao::getInstance()->getById($id);
        
        if (!$book) {
            return Response::asJson(['code' => 404, 'msg' => '书籍不存在']);
        }
        
        // 更新可编辑字段
        $editableFields = ['bookName', 'author', 'description', 'category', 'series', 'seriesNum', 'favorite', 'rate'];
        foreach ($editableFields as $field) {
            if (isset($data[$field])) {
                $book->$field = $data[$field];
            }
        }
        
        if (BookDao::getInstance()->update($book)) {
            return Response::asJson(['code' => 200, 'msg' => '更新成功']);
        }
        
        return Response::asJson(['code' => 500, 'msg' => '更新失败']);
    }
    
    /**
     * 删除书籍
     * POST /book/delete
     */
    public function delete(): Response
    {
        $id = intval($this->request->post('id', 0));
        
        if ($id <= 0) {
            return Response::asJson(['code' => 400, 'msg' => '参数错误']);
        }
        
        if (BookDao::getInstance()->deleteById($id)) {
            return Response::asJson(['code' => 200, 'msg' => '删除成功']);
        }
        
        return Response::asJson(['code' => 500, 'msg' => '删除失败']);
    }
    
    /**
     * WebDAV同步（预留接口）
     * POST /book/sync
     */
    public function sync(): Response
    {
        // TODO: 实现WebDAV同步逻辑
        return Response::asJson(['code' => 200, 'msg' => '同步功能暂未实现']);
    }

}