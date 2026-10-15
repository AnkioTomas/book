<?php

declare(strict_types=1);

namespace app\utils;

use nova\framework\core\Context;
use nova\framework\core\Instance;
use nova\plugin\http\HttpClient;

/**
 * 微信读书图书搜索。
 *
 * 走公开接口 weread.qq.com/web/search/global（无需登录 / API Key）。
 * 返回字段形状对齐 DoubanSearch，便于 AI 工具与编辑页选择器复用。
 */
class WereadSearch extends Instance
{
    private const SEARCH_URL = 'https://weread.qq.com/web/search/global';
    private const DEFAULT_COUNT = 10;

    /** 微信读书封面 CDN 可用的最大尺寸前缀（实测 t9_ ≈ 428×616；s_ 仅 ≈ 70×101） */
    private const COVER_HD_PREFIX = 't9_';

    /**
     * 把微信读书封面 URL 的 s_ / t\d+_ 文件名前缀换成高清 t9_。
     * $verify=true 时以 HEAD 探测 t9_ 是否存在（结果按 URL 缓存），不存在或探测失败则回退原 URL。
     * 非微信读书 CDN 的 URL 原样返回。
     */
    public static function hdCoverUrl(string $url, bool $verify = true): string
    {
        $url = trim($url);
        if ($url === '' || !str_contains($url, 'cdn.weread.qq.com/')) {
            return $url;
        }
        $hd = preg_replace('#/(?:s|t\d+)_([^/?\#]+)$#', '/' . self::COVER_HD_PREFIX . '$1', $url);
        if (!is_string($hd) || $hd === $url) {
            return $url;
        }
        if (!$verify) {
            return $hd;
        }

        $cache = Context::instance()->cache;
        $key = 'weread_cover_hd/' . md5($hd);
        $known = $cache->get($key);
        if ($known === 'y' || $known === 'n') {
            return $known === 'y' ? $hd : $url;
        }

        $ok = false;
        try {
            $resp = HttpClient::init()
                ->timeout(3)
                ->setOption(CURLOPT_NOBODY, true)
                ->setHeader('User-Agent', Douban::getRandomUserAgent())
                ->send($hd);
            $ok = $resp !== null && $resp->getHttpCode() === 200;
            // 仅对确定结果（200 / 404）长期缓存；网络异常不缓存，下次再试
            if ($resp !== null && in_array($resp->getHttpCode(), [200, 404], true)) {
                $cache->set($key, $ok ? 'y' : 'n', 86400 * 30);
            }
        } catch (\Throwable) {
            $ok = false;
        }
        return $ok ? $hd : $url;
    }

    private function headers(): array
    {
        return [
            'User-Agent' => Douban::getRandomUserAgent(),
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            'Referer' => 'https://weread.qq.com/',
            'Origin' => 'https://weread.qq.com',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $count = self::DEFAULT_COUNT): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        if ($count <= 0) {
            $count = self::DEFAULT_COUNT;
        }
        $count = min(20, $count);

        $client = HttpClient::init()
            ->timeout(30)
            ->cache(1800)
            ->gzip()
            ->setOption(CURLOPT_DNS_SERVERS, '223.5.5.5,223.6.6.6')
            ->setHeaders($this->headers())
            ->get();

        $response = $client->send(self::SEARCH_URL, [
            'keyword' => $query,
            'maxIdx' => 0,
            'count' => $count,
        ]);

        if (!$response || $response->getHttpCode() !== 200) {
            return [];
        }

        $json = json_decode($response->getBody(), true);
        if (!is_array($json) || empty($json['books']) || !is_array($json['books'])) {
            return [];
        }

        $out = [];
        foreach ($json['books'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $info = $row['bookInfo'] ?? null;
            if (!is_array($info) || trim((string)($info['title'] ?? '')) === '') {
                continue;
            }
            // 已下架的跳过
            if ((int)($info['soldout'] ?? 0) === 1) {
                continue;
            }
            $mapped = $this->mapBook($info, $row);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>      $info
     * @param  array<string, mixed>      $row
     * @return array<string, mixed>|null
     */
    private function mapBook(array $info, array $row): ?array
    {
        $title = trim((string)($info['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        // newRating：接口常见为 0–1000（930 ≈ 9.3/10）
        $rawRating = (float)($info['newRating'] ?? $row['newRating'] ?? 0);
        $rating10 = $rawRating > 0 ? round($rawRating / 100, 1) : 0.0;

        $intro = trim((string)($info['intro'] ?? ''));
        $category = trim((string)($info['category'] ?? ''));
        $tags = [];
        if ($category !== '') {
            // 经济理财-商业 → ["经济理财", "商业"]
            foreach (preg_split('/[-／\/|、,，]+/u', $category) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $tags[] = $part;
                }
            }
        }
        $ratingTitle = '';
        $detail = $info['newRatingDetail'] ?? $row['newRatingDetail'] ?? null;
        if (is_array($detail) && !empty($detail['title'])) {
            $ratingTitle = (string)$detail['title'];
            if ($ratingTitle !== '' && !in_array($ratingTitle, $tags, true)) {
                $tags[] = $ratingTitle;
            }
        }

        $publishTime = trim((string)($info['publishTime'] ?? ''));
        $year = '';
        if (preg_match('/(\d{4})/', $publishTime, $m)) {
            $year = $m[1];
        }

        $bookId = (string)($info['bookId'] ?? '');
        $url = trim((string)($info['deepLink'] ?? ''));
        if ($url === '' && $bookId !== '') {
            $url = 'https://weread.qq.com/web/reader/' . rawurlencode($bookId);
        }

        $price = $info['price'] ?? '';
        if (is_numeric($price) && (float)$price < 0) {
            $price = '';
        }

        return [
            'title' => $title,
            'author' => trim((string)($info['author'] ?? '')),
            'publisher' => trim((string)($info['publisher'] ?? '')),
            'year' => $year,
            'isbn' => trim((string)($info['isbn'] ?? '')),
            'rating' => $rating10 > 0 ? $rating10 : '',
            'intro' => $intro,
            'full_intro' => $intro,
            'tags' => $tags,
            'cover_url' => self::hdCoverUrl((string)($info['cover'] ?? '')),
            'url' => $url,
            'pages' => '',
            'series' => trim((string)($info['lPushName'] ?? '')),
            'price' => $price === '' ? '' : (string)$price,
            'source' => 'weread',
            'bookId' => $bookId,
            'rating_label' => $ratingTitle,
            'reading_count' => (int)($row['readingCount'] ?? $info['readingCount'] ?? 0),
        ];
    }
}
