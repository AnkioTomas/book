<?php

declare(strict_types=1);

namespace app\utils\meta;

use nova\plugin\http\HttpClient;

/**
 * 长佩文学：匿名网页 JSON 搜索（对齐月读 source/changpei）。
 */
final class ChangpeiSource implements MetaSource
{
    private const SEARCH = 'https://www.gongzicp.com/webapi/search/novels';

    public function id(): string
    {
        return 'changpei';
    }

    public function name(): string
    {
        return '长佩文学';
    }

    public function search(string $query): array
    {
        $client = HttpClient::init()
            ->timeout(20)
            ->cache(900)
            ->gzip()
            ->setHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Client' => 'pc',
                'Referer' => 'https://www.gongzicp.com/',
                'Accept' => 'application/json',
            ])
            ->get();

        $resp = $client->send(self::SEARCH, [
            'page' => 1,
            'k' => $query,
        ]);
        if (!$resp || $resp->getHttpCode() !== 200) {
            return [];
        }
        $wire = json_decode($resp->getBody(), true);
        if (!is_array($wire) || (int)($wire['code'] ?? 0) !== 200) {
            return [];
        }
        $list = $wire['data']['list'] ?? null;
        if (!is_array($list)) {
            return [];
        }

        $rows = [];
        foreach ($list as $item) {
            if (!is_array($item) || empty($item['novel_id'])) {
                continue;
            }
            $type = trim((string)($item['novel_type'] ?? ''));
            $cover = trim((string)($item['novel_cover'] ?? ''));
            if ($cover !== '' && str_starts_with($cover, '//')) {
                $cover = 'https:' . $cover;
            }
            $rows[] = [
                'title' => (string)($item['novel_name'] ?? ''),
                'author' => (string)($item['novel_author'] ?? ''),
                'intro' => (string)($item['novel_desc'] ?? ''),
                'cover_url' => $cover,
                'tags' => $type !== '' ? [$type] : [],
                'url' => 'https://www.gongzicp.com/novel-' . rawurlencode((string)$item['novel_id']) . '.html',
            ];
        }

        return MetaHit::mapList($rows, $this->id());
    }
}
