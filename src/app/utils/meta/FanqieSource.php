<?php

declare(strict_types=1);

namespace app\utils\meta;

use nova\plugin\http\HttpClient;

/**
 * 番茄小说：免登录搜索（对齐月读 source/fanqie）。
 */
final class FanqieSource implements MetaSource
{
    private const SEARCH = 'https://novel.snssdk.com/api/novel/channel/homepage/search/search/v1/';
    private const COVER_CDN = 'https://p6-novel.byteimg.com';

    public function id(): string
    {
        return 'fanqie';
    }

    public function name(): string
    {
        return '番茄小说';
    }

    public function search(string $query): array
    {
        $client = HttpClient::init()
            ->timeout(20)
            ->cache(900)
            ->gzip()
            ->setHeaders([
                'User-Agent' => 'com.dragon.read/6.5.3.32 (Linux; U; Android 13; zh_CN; VDE-AL10; Build/TP1A.220624.014;tt-ok/3.10.0.2)',
                'Accept' => 'application/json, text/plain, */*',
            ])
            ->get();

        $resp = $client->send(self::SEARCH, [
            'aid' => 1967,
            'offset' => 0,
            'q' => $query,
        ]);
        if (!$resp || $resp->getHttpCode() !== 200) {
            return [];
        }
        $wire = json_decode($resp->getBody(), true);
        if (!is_array($wire) || (int)($wire['code'] ?? -1) !== 0) {
            return [];
        }
        $list = $wire['data']['ret_data'] ?? null;
        if (!is_array($list)) {
            return [];
        }

        $rows = [];
        foreach ($list as $item) {
            if (!is_array($item) || empty($item['book_id'])) {
                continue;
            }
            $category = trim((string)($item['category'] ?? ''));
            $rows[] = [
                'title' => (string)($item['title'] ?? ''),
                'author' => (string)($item['author'] ?? ''),
                'intro' => (string)($item['abstract'] ?? ''),
                'cover_url' => self::mobileCover((string)($item['thumb_url'] ?? $item['audio_thumb_uri'] ?? '')),
                'tags' => $category !== '' ? [$category] : [],
                'url' => 'https://fanqienovel.com/page/' . rawurlencode((string)$item['book_id']),
            ];
        }

        return MetaHit::mapList($rows, $this->id());
    }

    private static function mobileCover(string $thumb): string
    {
        $thumb = trim($thumb);
        if ($thumb === '') {
            return '';
        }
        // 分隔符避开 #/~：PHP 里字符类内的分隔符仍会提前结束模式
        if (preg_match('%(novel-pic/[^~?#]+|novel-static/[^~?#]+)%', $thumb, $m)) {
            return self::COVER_CDN . '/' . $m[1] . '~tplv-shrink:320:0.image';
        }
        if (preg_match('%^https?://%', $thumb)) {
            return $thumb;
        }
        return self::COVER_CDN . '/' . ltrim($thumb, '/') . '~tplv-shrink:320:0.image';
    }
}
