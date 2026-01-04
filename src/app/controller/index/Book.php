<?php

namespace app\controller\index;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use app\utils\BookManager;
use nova\framework\cache\Cache;
use nova\framework\core\Context;
use nova\framework\http\Response;
use function nova\framework\dump;

class Book extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 获取书籍列表（支持分页、搜索、筛选）
     * GET /book/list?page=1&pageSize=20&search=xxx&series=xxx&category=xxx&favorite=xxx
     */
    public function list(): Response
    {
        $page = intval($this->request->get('page', 1));
        $limit = intval($this->request->get('pageSize', 20));
        $search = trim($this->request->get('search', ''));
        $series = trim($this->request->get('series', ''));
        $category = trim($this->request->get('category', ''));
        $favorite = trim($this->request->get('favorite', ''));
        
        $result = BookDao::getInstance()->getList($page, $limit, $search, $series, $category, $favorite);
        
        return Response::asJson([
            'code' => 200,
            'msg' => 'success',
            'data' => $result['list'],
            'count' => $result['total']
        ]);
    }
    
    /**
     * 获取筛选选项
     * GET /book/filters
     */
    public function filters(): Response
    {
        return Response::asJson([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'groupNames' => BookDao::getInstance()->getSeriesNames(),
                'categories' => BookDao::getInstance()->getCategories(),
                'favorites' => BookDao::getInstance()->getFavorites()
            ]
        ]);
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
        $editableFields = ['bookName', 'author', 'description', 'category', 'series', 'seriesNum', 'favorite', 'rate','coverUrl'];
        foreach ($editableFields as $field) {
            if (isset($data[$field])) {
                $book->$field = $data[$field];
            }
        }
        if(!empty($book->coverUrl)){
            Context::instance()->cache->set("coverUrl/{$book->coverUrl}",true);
        }

        
        if (BookDao::getInstance()->updateModel($book)) {
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

        $book = BookDao::getInstance()->getById($id);
        BookManager::instance()->deleteBook($book->filename);
        BookManager::instance()->deleteCover($book->filename);
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
        $list = BookManager::instance()->list();
        /**
         * @var $book BookModel
         */
        foreach ($list as $book) {
            if ($book->id > 0){
                $dbBook = BookDao::getInstance()->getById($book->id);
                if (!$dbBook) continue;
                
                // 差异比较：用 $book 的非空值填充 $dbBook 的空字段
                $needUpdate = false;
                $fields = ['bookName', 'author', 'description',  'series', 'seriesNum', 'favorite', 'rate', 'coverUrl'];
                
                foreach ($fields as $field) {
                    if (!empty($book->$field) && empty($dbBook->$field)) {
                        $dbBook->$field = $book->$field;
                        $needUpdate = true;
                    }
                }
                
                if ($needUpdate) {
                    BookDao::getInstance()->updateModel($dbBook);
                }
            }else{
                $book->splitCategory2Series();
               try{
                   $filename = $book->filename;
                   if (BookManager::instance()->bookExists($filename)) {
                       BookDao::getInstance()->insertModel($book);
                   }
               }catch (\Exception $e){
               }
            }
        }

        $books = BookDao::getInstance()->select()->commit();

        foreach ($books as &$book) {
            $book = $book->pushSeries2Category();
            if (!empty($book->coverUrl) && Context::instance()->cache->get("coverUrl/{$book->coverUrl}")){
                $file = BookManager::proxy($book->coverUrl);
                if(BookManager::instance()->uploadCover($file,$book->filename)){
                    Context::instance()->cache->delete("coverUrl/{$book->coverUrl}");
                }
            }
        }



        BookManager::instance()->push($books);

        return Response::asJson(['code' => 200, 'msg' => '同步完成','data'=>$books]);
    }
    
    /**
     * 删除重复书籍（根据书名+作者判断，保留最早导入的）
     * POST /book/removeDuplicates
     */
    public function removeDuplicates(): Response
    {
        // 获取所有书籍
        $allBooks = BookDao::getInstance()->select()->commit();
        
        // 按 bookName + author 分组
        $groups = [];
        foreach ($allBooks as $book) {
            $key = trim($book->bookName) . '|' . trim($book->author);
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $book;
        }
        
        // 找出重复的书籍并删除（保留最早的）
        $deletedCount = 0;
        $deletedBooks = [];
        
        foreach ($groups as $key => $books) {
            if (count($books) <= 1) {
                continue; // 没有重复，跳过
            }
            
            // 找出 addTime 最小的（最早导入的）
            $oldest = $books[0];
            foreach ($books as $book) {
                if ($book->addTime < $oldest->addTime) {
                    $oldest = $book;
                }
            }
            
            // 删除所有不是最老的书籍
            foreach ($books as $book) {
                if ($book->id === $oldest->id) {
                    continue; // 保留最老的
                }
                
                BookManager::instance()->deleteBook($book->filename);
                BookManager::instance()->deleteCover($book->filename);
                BookDao::getInstance()->deleteById($book->id);
                
                $deletedBooks[] = [
                    'id' => $book->id,
                    'bookName' => $book->bookName,
                    'author' => $book->author,
                    'addTime' => $book->addTime
                ];
                $deletedCount++;
            }
        }
        
        return Response::asJson([
            'code' => 200,
            'msg' => $deletedCount > 0 ? "已删除 {$deletedCount} 本重复书籍" : '未发现重复书籍',
            'data' => [
                'deletedCount' => $deletedCount,
                'deletedBooks' => $deletedBooks
            ]
        ]);
    }

}