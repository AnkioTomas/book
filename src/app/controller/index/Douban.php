<?php

declare(strict_types=1);

namespace app\controller\index;

use app\database\dao\BookDao;
use app\utils\BookManager\CoverManager;
use app\utils\Douban as DoubanUtil;
use app\utils\DoubanSearch;
use nova\framework\http\Response;
use nova\plugin\login\controller\BaseAPIController;

/**
 * 豆瓣图书搜索控制器。
 *
 * 搜索逻辑统一收敛到 app\utils\DoubanSearch，控制器只负责 HTTP 入出参。
 */
class Douban extends BaseAPIController
{
    /**
     * 搜索豆瓣图书
     *
     * @return Response JSON格式的书籍信息
     */
    public function search(): Response
    {
        $query = $this->request->post('q', '');

        if (empty($query)) {
            return Response::asJson([
                'code' => 400,
                'msg' => '搜索关键词不能为空',
            ]);
        }

        $results = DoubanSearch::getInstance()->search($query);

        if (empty($results)) {
            return Response::asJson([
                'code' => 404,
                'msg' => '未找到匹配的书籍',
            ]);
        }

        return Response::asJson([
            'code' => 200,
            'data' => $results,
        ]);
    }

    public function proxy(string $uri): Response
    {
        $file = DoubanUtil::download($uri);
        return Response::asStatic($file);
    }

    public function webdav(string $filename): Response
    {
        $filename = rawurldecode($filename);
        $book = BookDao::getInstance()->getByFileName($filename);
        if (empty($book)) {
            return Response::asText('404 not found');
        }
        if (empty($book->coverUrl)) {
            return Response::asStatic(CoverManager::getInstance()->getCover($filename));
        }
        $file = DoubanUtil::download($book->coverUrl);
        return Response::asStatic($file);
    }
}
