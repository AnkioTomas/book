<?php

declare(strict_types=1);

namespace app\controller\index;

use app\controller\ApiController;
use app\database\dao\BookDao;
use app\utils\BookManager\CoverManager;
use app\utils\Douban as DoubanUtil;
use app\utils\DoubanSearch;
use nova\framework\http\Response;

/**
 * 豆瓣图书搜索控制器。
 *
 * 搜索逻辑统一收敛到 app\utils\DoubanSearch，控制器只负责 HTTP 入出参。
 */
class Douban extends ApiController
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
        $filename = str_replace('\\', '/', trim($filename));
        $filename = ltrim($filename, '/');

        $book = BookDao::getInstance()->resolveByFilename($filename);
        if ($book !== null) {
            if ($book->coverUrl !== '') {
                $file = DoubanUtil::download($book->coverUrl);
                if ($file !== '') {
                    return Response::asStatic($file);
                }
            }
            // 封面 sidecar 按 basename 存，用书库真实 filename
            $file = CoverManager::getInstance()->getCover($book->filename);
            if ($file !== '') {
                return Response::asStatic($file);
            }
            return Response::asText('404 not found', code: 404);
        }

        // 书库无记录：仍尝试按 basename 取本地/WebDAV 封面缓存
        $file = CoverManager::getInstance()->getCover($filename);
        if ($file !== '') {
            return Response::asStatic($file);
        }

        return Response::asText('404 not found', code: 404);
    }
}
