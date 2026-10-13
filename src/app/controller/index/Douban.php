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
    private const array COVER_COLORS = [
        '#8b2e3a', '#3b5a80', '#2b2b2b', '#5e6b73',
        '#6b4226', '#9e3a6e', '#2e6b4e', '#c47a2c',
    ];

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
            // 本地 sidecar 是同步任务准备好的权威缓存，必须先读；不能每次首页请求
            // 都阻塞在第三方封面站点上。
            $file = CoverManager::getInstance()->getCover($book->filename);
            if ($file !== '') {
                return Response::asStatic($file);
            }
            if ($book->coverUrl !== '') {
                $file = DoubanUtil::download($book->coverUrl);
                if ($file !== '') {
                    return Response::asStatic($file);
                }
            }
            return $this->defaultCover($book->bookName ?: pathinfo($book->filename, PATHINFO_FILENAME), $book->author);
        }

        // 书库无记录：仍尝试按 basename 取本地/WebDAV 封面缓存
        $file = CoverManager::getInstance()->getCover($filename);
        if ($file !== '') {
            return Response::asStatic($file);
        }

        return $this->defaultCover(pathinfo($filename, PATHINFO_FILENAME), '');
    }

    /**
     * 无封面时的默认封面：书名哈希定底色，竖排书名，底部作者。
     */
    private function defaultCover(string $title, string $author): Response
    {
        $color = self::COVER_COLORS[crc32($title) % count(self::COVER_COLORS)];
        $len = mb_strlen($title);
        $size = $len <= 5 ? 44 : ($len <= 12 ? 34 : 26);
        $title = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES);
        $author = htmlspecialchars(mb_strimwidth($author, 0, 24, '…'), ENT_XML1 | ENT_QUOTES);
        $font = "'PingFang SC','Microsoft YaHei','Noto Sans CJK SC',sans-serif";

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 400">
<rect width="300" height="400" fill="{$color}"/>
<rect x="16" y="16" width="268" height="368" fill="none" stroke="#f5ecd7" stroke-opacity=".8" stroke-width="1.5"/>
<foreignObject x="30" y="68" width="240" height="252">
<div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;justify-content:center;height:100%;overflow:hidden">
<div style="writing-mode:vertical-rl;max-height:100%;font:{$size}px/1.2 {$font};letter-spacing:6px;color:#fff">{$title}</div>
</div>
</foreignObject>
<text x="150" y="360" text-anchor="middle" font-family="{$font}" font-size="16" fill="#fff" fill-opacity=".85">{$author}</text>
</svg>
SVG;

        return Response::asRaw($svg, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
