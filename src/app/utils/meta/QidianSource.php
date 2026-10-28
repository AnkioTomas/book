<?php

declare(strict_types=1);

namespace app\utils\meta;

use nova\plugin\http\HttpClient;

/**
 * 起点中文网：手机站 SSR 搜索页（对齐月读 source/qidian）。
 */
final class QidianSource implements MetaSource
{
    public function id(): string
    {
        return 'qidian';
    }

    public function name(): string
    {
        return '起点中文网';
    }

    public function search(string $query): array
    {
        $url = 'https://m.qidian.com/soushu/' . rawurlencode($query) . '.html';
        $client = HttpClient::init()
            ->timeout(20)
            ->cache(900)
            ->gzip()
            ->setHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
                'Accept' => 'text/html,application/xhtml+xml',
                'Referer' => 'https://m.qidian.com/',
            ])
            ->get();

        $resp = $client->send($url);
        if (!$resp || $resp->getHttpCode() !== 200) {
            return [];
        }
        $html = $resp->getBody();
        if (!preg_match('#id="vite-plugin-ssr_pageContext" type="application/json">(.*?)</script>#s', $html, $m)) {
            return [];
        }
        $wire = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
        if (!is_array($wire)) {
            return [];
        }
        $props = $wire['pageContext']['pageProps'] ?? $wire['pageProps'] ?? null;
        $data = is_array($props) ? ($props['pageData'] ?? null) : null;
        if (!is_array($data)) {
            return [];
        }
        $records = $data['bookInfo']['records'] ?? null;
        if (!is_array($records)) {
            return [];
        }

        $rows = [];
        foreach ($records as $row) {
            if (!is_array($row) || empty($row['bid'])) {
                continue;
            }
            $id = (string)$row['bid'];
            $author = (string)($row['bAuth'] ?? '');
            if (str_ends_with($author, '...')) {
                $author = '';
            }
            $cat = trim((string)($row['cat'] ?? ''));
            $rows[] = [
                'title' => (string)($row['bName'] ?? ''),
                'author' => $author,
                'intro' => strip_tags((string)($row['desc'] ?? '')),
                'cover_url' => 'https://bookcover.yuewen.com/qdbimg/349573/' . rawurlencode($id) . '/180',
                'tags' => $cat !== '' ? [$cat] : [],
                'url' => 'https://m.qidian.com/book/' . rawurlencode($id) . '/',
            ];
        }

        return MetaHit::mapList($rows, $this->id());
    }
}
