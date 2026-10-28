<?php

declare(strict_types=1);

namespace app\utils\meta;

use nova\plugin\http\HttpClient;

/**
 * 晋江文学城：匿名 App 搜索接口（对齐月读 source/jjwxc）。
 */
final class JjwxcSource implements MetaSource
{
    private const SEARCH = 'https://app-cdn.jjwxc.net/androidapi/search';

    public function id(): string
    {
        return 'jjwxc';
    }

    public function name(): string
    {
        return '晋江文学城';
    }

    public function search(string $query): array
    {
        $client = HttpClient::init()
            ->timeout(20)
            ->cache(900)
            ->gzip()
            ->setHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 5.1; Lenovo) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/39.0.0.0 Mobile Safari/537.36/JINJIANG-Android/206(Lenovo;android 5.1;Scale/2.0)',
                'Referer' => 'http://android.jjwxc.net?v=206',
                'Accept' => 'application/json',
            ])
            ->get();

        $resp = $client->send(self::SEARCH, [
            'keyword' => $query,
            'type' => 1,
            'page' => 1,
            'versionCode' => 206,
        ]);
        if (!$resp || $resp->getHttpCode() !== 200) {
            return [];
        }
        $wire = json_decode($resp->getBody(), true);
        if (!is_array($wire) || empty($wire['items']) || !is_array($wire['items'])) {
            return [];
        }

        $rows = [];
        foreach ($wire['items'] as $item) {
            if (!is_array($item) || empty($item['novelid'])) {
                continue;
            }
            $cover = trim((string)($item['ebookurl'] ?? ''));
            if ($cover !== '' && str_starts_with($cover, '//')) {
                $cover = 'https:' . $cover;
            }
            $rows[] = [
                'title' => (string)($item['novelname'] ?? ''),
                'author' => (string)($item['authorname'] ?? ''),
                'intro' => strip_tags((string)($item['novelintroshort'] ?? '')),
                'cover_url' => $cover,
                'url' => 'https://www.jjwxc.net/onebook.php?novelid=' . rawurlencode((string)$item['novelid']),
            ];
        }

        return MetaHit::mapList($rows, $this->id());
    }
}
