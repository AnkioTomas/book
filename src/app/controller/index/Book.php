<?php

namespace app\controller\index;

use app\database\dao\BookDao;
use app\database\dao\ReadingProgressDao;
use app\database\model\BookModel;
use app\database\model\ReadingProgressModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookManager\MoonManager;
use app\utils\BookManager\ProgressManager;
use app\utils\MoonBookManager;
use app\utils\BookOrganizer\Parser;
use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\http\Response;

class Book extends BaseController
{

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
        $finished = trim($this->request->get('finished', ''));
        
        $result = BookDao::getInstance()->getList($page, $limit, $search, $series, $category, $favorite, $finished);
        $books = $result['list'];
        $filenames = [];
        foreach ($books as $book) {
            if (!empty($book->filename)) {
                $filenames[] = $book->filename;
            }
        }
        
        $progressMap = [];
        if (!empty($filenames)) {
            $progressList = ReadingProgressDao::getInstance()->getByFilenames($filenames);
            foreach ($progressList as $progress) {
                $progressMap[$progress->filename] = $progress;
            }
        }
        
        $rows = [];
        foreach ($books as $book) {
            $row = $book->toArray();
            $row['progressRaw'] = '';
            $row['progressText'] = '';
            $row['progressPercent'] = 0.0;
            $progress = $progressMap[$book->filename] ?? null;
            if ($progress) {
                $row['progressRaw'] = $progress->raw;
                $row['progressText'] = $progress->percentText . '%';
                $row['progressPercent'] = $progress->percent;
            }
            $rows[] = $row;
        }
        
        return Response::asJson([
            'code' => 200,
            'msg' => 'success',
            'data' => $rows,
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
     * 获取阅读进度
     * GET /book/progress?filename=xxx.epub
     */
    public function progress(): Response
    {
        $filename = rawurldecode($this->request->get('filename', ''));
        $filename = trim($filename);
        if ($filename === '') {
            return Response::asJson(['code' => 400, 'msg' => '参数错误', 'data' => []]);
        }

        $progress = ProgressManager::getInstance()->getProgressText($filename);
        if (empty($progress)) {
            return Response::asJson(['code' => 200, 'msg' => 'success', 'data' => []]);
        }

        $item = ReadingProgressModel::fromString($progress);
        ReadingProgressDao::getInstance()->updateItem($filename,$item);

        return Response::asJson([
            'code' => 200,
            'msg' => 'success',
            'data' => $item->percent,
        ]);
    }

    /**
     * 与 progressSync 等价，供需要独立路由的客户端使用
     * POST /book/progressUpdate
     */
    public function progressUpdate(): Response
    {
        $data  = $this->request->post();
        $filename = rawurldecode($data['filename'] ?? '');
        $filename = trim($filename);

        $frac = $data["frac"] ?? 0;
        $frac = round(floatval($frac), 2);

        $spine  = $data["spine"] ?? 0;
        $spine = intval($spine);

        $page  = $data["page"] ?? 0;
        $page = intval($page);

        $percent = $data["percent"] ?? '0%';

        $progress = ReadingProgressDao::getInstance()->getByFilename($filename);
        if (empty($progress)) {
            $progress = new ReadingProgressModel();
            $progress->filename = $filename;
            $progress->id = ReadingProgressDao::getInstance()->insertModel($progress);
        }

        $progress->percent = $frac;
        $progress->spineIndex   = $spine;
        $progress->percentText = $percent;
        $progress->pageIndex = $page;

        ReadingProgressDao::getInstance()->updateModel($progress);

        return Response::asJson([
            'code' => 200,
        ]);
    }


    /**
     * 获取 EPUB 文件用于在线阅读
     * GET /book/file?filename=xxx.epub
     */
    public function file(): Response
    {
        $filename = rawurldecode($this->request->get('filename', ''));
        if (empty($filename)) {
            return Response::asText('参数错误');
        }

        $book = BookDao::getInstance()->getByFileName($filename);
        if (!$book) {
            return Response::asText('404 not found');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['epub', 'mobi', 'azw', 'azw3', 'pdf'];
        if (!in_array($ext, $allowed, true)) {
            return Response::asText('不支持的文件格式');
        }

        $cacheDir = RUNTIME_PATH . DS . 'reader';
        File::mkDir($cacheDir);
        $localPath = $cacheDir . DS . basename($filename);

        if (!file_exists($localPath) || filesize($localPath) < 100 * 1024) {
            if (!MoonBookManager::instance()->downloadBook($filename, $localPath)) {
                return Response::asText('下载失败');
            }
        }
        
        return Response::asStatic($localPath);
    }

    public function reader():Response
    {
        $filename = rawurldecode($this->request->get('file', ''));
        $filename = trim($filename);
        if ($filename === '') {
            return $this->redirectTo("/404");
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $supported = ['epub', 'pdf', 'mobi', 'azw', 'azw3'];
        if (!in_array($ext, $supported, true)) {
            return $this->redirectTo("/403");
        }
        $progress = ReadingProgressDao::getInstance()->getByFilename($filename);
        if ($progress == null) {
            $progress =  new ReadingProgressModel();
        }

        $bookUrl = '/admin/api/book/file?filename=' . rawurlencode($filename);
        $readerUrl = '/static/foliate/reader.html?url=' . rawurlencode($bookUrl)
            . '&filename=' . rawurlencode($filename)
            . '&frac=' . $progress->percent;

        return $this->redirectTo($readerUrl);
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
            BookDao::getInstance()->syncBooks();
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
        MoonBookManager::getInstance()->delete($book->filename);
        if (BookDao::getInstance()->deleteById($id)) {
            BookDao::getInstance()->syncBooks();
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
        BookDao::getInstance()->syncBooks(true);

        return Response::asJson(['code' => 200, 'msg' => '同步完成']);
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

                MoonBookManager::getInstance()->delete($book->filename);
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

        BookDao::getInstance()->syncBooks();
        
        return Response::asJson([
            'code' => 200,
            'msg' => $deletedCount > 0 ? "已删除 {$deletedCount} 本重复书籍" : '未发现重复书籍',
            'data' => [
                'deletedCount' => $deletedCount,
                'deletedBooks' => $deletedBooks
            ]
        ]);
    }

    /**
     * 刮削封面
     */
    public function scrapeCover(): Response
    {
        $id = intval($this->request->post('id', 0));
        if ($id <= 0) return Response::asJson(['code' => 400, 'msg' => '参数错误']);
        
        $book = BookDao::getInstance()->getById($id);
        if (!$book) return Response::asJson(['code' => 404, 'msg' => '书籍不存在']);
        
        $bookManager = MoonBookManager::instance();
        
        // 下载书籍到临时目录
        $tempPath = RUNTIME_PATH . DS . 'temp' . DS . $book->filename;
        if (!$bookManager->downloadBook($book->filename, $tempPath)) {
            return Response::asJson(['code' => 500, 'msg' => '下载书籍失败']);
        }
        
        // 提取封面
        $coverPath = Parser::cover($tempPath, $book);

        if (empty($coverPath)) {
            File::del($tempPath);
            return Response::asJson(['code' => 500, 'msg' => '提取封面失败']);
        }
        
        // 上传封面
        if (!CoverManager::getInstance()->uploadCover($coverPath, $book->filename)) {
            File::del($tempPath);
            return Response::asJson(['code' => 500, 'msg' => '上传封面失败']);
        }
        File::del($tempPath);
        return Response::asJson(['code' => 200, 'msg' => '刮削成功']);
    }

}