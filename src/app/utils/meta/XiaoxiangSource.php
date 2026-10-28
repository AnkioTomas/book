<?php

declare(strict_types=1);

namespace app\utils\meta;

use nova\plugin\http\HttpClient;

/**
 * 潇湘书院：网页搜索 HTML（对齐月读 source/xiaoxiang）。
 */
final class XiaoxiangSource implements MetaSource
{
    public function id(): string
    {
        return 'xiaoxiang';
    }

    public function name(): string
    {
        return '潇湘书院';
    }

    public function search(string $query): array
    {
        $url = 'https://www.xxsy.net/search?sort=0&s_wd=' . rawurlencode($query);
        $client = HttpClient::init()
            ->timeout(20)
            ->cache(900)
            ->gzip()
            ->setHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
                'Referer' => 'https://www.xxsy.net/',
            ])
            ->get();

        $resp = $client->send($url);
        if (!$resp || $resp->getHttpCode() !== 200) {
            return [];
        }
        $html = $resp->getBody();
        $rows = [];
        if (preg_match_all('#<li[^>]*>(.*?)</li>#s', $html, $items)) {
            foreach ($items[1] as $item) {
                if (!preg_match('#<h4><a href="/book/(\d+)"[^>]*>([^<]*)</a>#', $item, $m)) {
                    continue;
                }
                $sub = '';
                if (preg_match('#<span class="subtitle">(.*?)</h4>#s', $item, $sm)) {
                    if (preg_match('#</i>([^<]*)</a>#', $sm[1], $am)) {
                        $sub = html_entity_decode(trim($am[1]), ENT_QUOTES | ENT_HTML5);
                    }
                }
                $cover = '';
                if (preg_match('#<img src="([^"]*)"#', $item, $cm)) {
                    $cover = $cm[1];
                    if (str_starts_with($cover, '//')) {
                        $cover = 'https:' . $cover;
                    }
                }
                $rows[] = [
                    'title' => html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5),
                    'author' => $sub,
                    'cover_url' => $cover,
                    'url' => 'https://www.xxsy.net/book/' . $m[1],
                ];
            }
        }

        return MetaHit::mapList($rows, $this->id());
    }
}
