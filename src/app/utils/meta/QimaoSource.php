<?php

declare(strict_types=1);

namespace app\utils\meta;

use nova\plugin\http\HttpClient;

/**
 * 七猫小说：匿名 App 搜索（签名协议对齐月读 source/qimao/protocol）。
 */
final class QimaoSource implements MetaSource
{
    private const BC = 'https://api-bc.wtzw.com';
    private const SIGN_KEY = 'd3dGiJc651gSQ8w1';
    private const CDN = 'https://cdn.wtzw.com';
    private const VERSIONS = ['73720', '73700', '73620', '73600', '73500', '73420', '73400', '73328', '73325', '73320'];

    public function id(): string
    {
        return 'qimao';
    }

    public function name(): string
    {
        return '七猫小说';
    }

    public function search(string $query): array
    {
        $params = [
            'extend' => '',
            'tab' => '0',
            'gender' => '0',
            'refresh_state' => '8',
            'page' => '1',
            'wd' => $query,
            'is_short_story_user' => '0',
        ];
        $params['sign'] = self::sign($params);
        $headers = [
            'AUTHORIZATION' => '',
            'app-version' => self::VERSIONS[0],
            'application-id' => 'com.****.reader',
            'channel' => 'unknown',
            'net-env' => '1',
            'platform' => 'android',
            'qm-params' => '',
            'reg' => '0',
        ];
        $headers['sign'] = self::sign($headers);

        $client = HttpClient::init()
            ->timeout(20)
            ->cache(900)
            ->gzip()
            ->setHeaders(array_merge($headers, ['Accept' => 'application/json']))
            ->get();

        $resp = $client->send(self::BC . '/search/v1/words', $params);
        if (!$resp || $resp->getHttpCode() !== 200) {
            return [];
        }
        $wire = json_decode($resp->getBody(), true);
        if (!is_array($wire) || !is_array($wire['data'] ?? null)) {
            return [];
        }
        $books = $wire['data']['books'] ?? null;
        if (!is_array($books)) {
            return [];
        }

        $rows = [];
        foreach ($books as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (string)($item['id'] ?? $item['book_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $title = (string)($item['original_title'] ?? $item['title'] ?? $item['book_title'] ?? '');
            $author = (string)($item['original_author'] ?? $item['author'] ?? '');
            $intro = strip_tags((string)($item['intro'] ?? ''));
            $cover = (string)($item['image_link'] ?? '');
            if ($cover !== '' && str_starts_with($cover, '/')) {
                $cover = self::CDN . $cover;
            }
            $cat = trim((string)($item['category2_name'] ?? $item['ptags'] ?? ''));
            $rows[] = [
                'title' => $title,
                'author' => $author,
                'intro' => $intro,
                'cover_url' => $cover,
                'tags' => $cat !== '' ? [$cat] : [],
                'url' => 'https://www.qimao.com/shuku/' . rawurlencode($id) . '/',
            ];
        }

        return MetaHit::mapList($rows, $this->id());
    }

    /** @param array<string, string> $params */
    private static function sign(array $params): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign') {
                continue;
            }
            $parts[] = $k . '=' . $v;
        }
        return md5(implode('', $parts) . self::SIGN_KEY);
    }
}
