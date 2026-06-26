<?php

declare(strict_types=1);

namespace app\utils;

use DOMDocument;
use DOMXPath;
use nova\framework\core\Context;
use nova\framework\core\Instance;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpResponse;
use nova\plugin\http\MultiHttp;

/**
 * 豆瓣图书搜索服务。
 *
 * 从 Douban 控制器抽出的共享逻辑：搜索结果页解析 + 并发抓取详情 + 综合评分排序。
 * 控制器与 AI 工具共用本服务，避免 HTML 解析逻辑双份维护。
 */
class DoubanSearch extends Instance
{
    private const SEARCH_URL = 'https://www.douban.com/search';
    private const MIN_SIMILARITY = 0.6;

    /**
     * 在豆瓣搜索书籍，返回按综合分排序的书籍数组（命中缓存优先）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        $data = Context::instance()->cache->get("search/$query");
        if (!empty($data)) {
            usort($data, fn ($a, $b) => $this->calculateScore($b) <=> $this->calculateScore($a));
            return $data;
        }

        $client = HttpClient::init()
            ->timeout(300)
            ->gzip()
            ->setOption(CURLOPT_DNS_SERVERS, '223.5.5.5,223.6.6.6')
            ->setHeader('User-Agent', Douban::getRandomUserAgent())
            ->setHeader('X-Forwarded-For', Douban::getRandomIP())
            ->get();

        $response = $client->send(self::SEARCH_URL, [
            'cat' => '1001',  // 图书分类
            'q' => $query,
        ]);

        if (!$response || $response->getHttpCode() !== 200) {
            return [];
        }

        $books = $this->parseSearchResults($response->getBody(), $query);
        if (empty($books)) {
            return [];
        }

        // 并发抓取详情页：单进程 curl_multi，结果直接收进本地数组，无需借 cache 跨进程中转
        $byUrl = [];
        foreach ($books as $book) {
            if (!empty($book['url'])) {
                $byUrl[$book['url']] = $book;
            }
        }

        $detailClient = HttpClient::init()
            ->timeout(300)
            ->gzip()
            ->setOption(CURLOPT_DNS_SERVERS, '223.5.5.5,223.6.6.6')
            ->setHeader('User-Agent', Douban::getRandomUserAgent())
            ->setHeader('X-Forwarded-For', Douban::getRandomIP())
            ->get();

        $doubans = [];
        (new MultiHttp(array_keys($byUrl), 5, $detailClient))->execute(
            function (string $url, HttpResponse $response) use (&$doubans, $byUrl): void {
                if ($response->getHttpCode() === 200) {
                    $doubans[] = array_merge($byUrl[$url], $this->parseBookDetail($response->getBody()));
                }
            }
        );

        usort($doubans, fn ($a, $b) => $this->calculateScore($b) <=> $this->calculateScore($a));

        Context::instance()->cache->set("search/$query", $doubans);

        return $doubans;
    }

    /**
     * 解析搜索结果页面
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSearchResults(string $html, string $query): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $results = $xpath->query("//div[@class='result-list']//div[@class='result']");

        $books = [];
        foreach ($results as $result) {
            $book = $this->parseSearchItem($xpath, $result, $query);
            if ($book && $book['similarity'] >= self::MIN_SIMILARITY) {
                $books[] = $book;
            }
        }

        usort($books, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($books, 0, 10);
    }

    /**
     * 解析单个搜索结果项
     *
     * @return array<string, mixed>|null
     */
    private function parseSearchItem(DOMXPath $xpath, \DOMElement $result, string $query): ?array
    {
        $titleNode = $xpath->query(".//div[@class='title']//h3/a", $result)->item(0);
        if (!$titleNode) {
            return null;
        }

        $title = trim($titleNode->textContent);
        $bookUrl = $titleNode->getAttribute('href');

        $subjectId = $this->extractSubjectId($bookUrl);
        if (!$subjectId) {
            return null;
        }

        $similarity = $this->calculateSimilarity($query, $title);

        $subjectCast = $xpath->query(".//div[@class='rating-info']//div[@class='subject-cast']", $result)->item(0);
        $info = $this->parsePublishInfo($subjectCast ? $subjectCast->textContent : '');

        $coverNode = $xpath->query(".//div[@class='pic']//img", $result)->item(0);
        $coverUrl = $coverNode?->getAttribute('src');

        $ratingNode = $xpath->query(".//div[@class='rating-info']//span[@class='rating_nums']", $result)->item(0);
        $rating = $ratingNode ? trim($ratingNode->textContent) : null;

        $introNode = $xpath->query(".//div[@class='content']/p", $result)->item(0);
        $intro = $introNode ? trim($introNode->textContent) : null;

        return [
            'title' => $title,
            'author' => $info['author'] ?? '',
            'publisher' => $info['publisher'] ?? '',
            'year' => $info['year'] ?? '',
            'cover_url' => $coverUrl,
            'rating' => $rating,
            'intro' => $intro,
            'url' => "https://book.douban.com/subject/{$subjectId}/",
            'douban_id' => $subjectId,
            'similarity' => $similarity,
        ];
    }

    private function extractSubjectId(string $url): ?string
    {
        if (str_contains($url, 'link2')) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            $realUrl = urldecode($params['url'] ?? '');
        } else {
            $realUrl = $url;
        }

        if (preg_match('/subject\/(\d+)/', $realUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 解析出版信息字符串，格式：作者 / 出版社 / 出版年 / 价格
     *
     * @return array<string, string>
     */
    private function parsePublishInfo(string $text): array
    {
        $parts = array_map('trim', explode('/', $text));

        $info = [
            'author' => $parts[0] ?? '',
            'publisher' => '',
            'year' => '',
        ];

        if (count($parts) > 2) {
            $info['publisher'] = $parts[count($parts) - 2];
        }

        foreach ($parts as $part) {
            if (preg_match('/^\d{4}$/', $part)) {
                $info['year'] = $part;
                break;
            }
        }

        return $info;
    }

    /** 计算字符串相似度 (0-1) */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $s1 = str_replace(' ', '', mb_strtolower($str1));
        $s2 = str_replace(' ', '', mb_strtolower($str2));

        similar_text($s1, $s2, $percent);

        return $percent / 100;
    }

    /**
     * 计算书籍综合评分 (0-100)
     *
     * @param array<string, mixed> $book
     */
    private function calculateScore(array $book): float
    {
        $similarityScore = ($book['similarity'] ?? 0) * 70;
        $completenessScore = $this->calculateCompleteness($book) * 30;

        return $similarityScore + $completenessScore;
    }

    /**
     * 计算字段完整度 (0-1)
     *
     * @param array<string, mixed> $book
     */
    private function calculateCompleteness(array $book): float
    {
        $fields = [
            'author' => 0.35,
            'full_intro' => 0.25,
            'cover_url' => 0.15,
            'rating' => 0.15,
            'isbn' => 0.05,
            'publisher' => 0.03,
            'year' => 0.02,
        ];

        $score = 0;
        foreach ($fields as $field => $weight) {
            if (!empty($book[$field])) {
                $score += $weight;
            }
        }

        return $score;
    }

    /**
     * 解析图书详情页
     *
     * @return array<string, mixed>
     */
    private function parseBookDetail(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $infoNode = $xpath->query("//div[@id='info']")->item(0);
        $infoText = $infoNode ? $infoNode->textContent : '';
        $infoText = implode("\n", array_filter(array_map('trim', explode("\n", $infoText))));

        $detail = [
            'isbn' => $this->extractField($infoText, 'ISBN'),
            'pages' => $this->extractField($infoText, '页数'),
            'price' => $this->extractField($infoText, '定价'),
            'binding' => $this->extractField($infoText, '装帧'),
            'series' => $this->extractField($infoText, '丛书'),
            'author' => $this->extractField($infoText, '作者'),
            'year' => $this->extractField($infoText, '出版年'),
            'transition' => $this->extractField($infoText, '译者'),
        ];

        $introNode = $xpath->query("//div[@id='link-report']//div[@class='intro']")->item(0);
        if ($introNode) {
            $detail['full_intro'] = trim($introNode->textContent);
        }

        $tagNodes = $xpath->query("//a[@class='tag']");
        $tags = [];
        foreach ($tagNodes as $tagNode) {
            $tags[] = trim($tagNode->textContent);
        }
        $detail['tags'] = array_unique($tags);

        return array_filter($detail);
    }

    private function extractField(string $text, string $field): ?string
    {
        if (preg_match("/{$field}[：:]\s*([^\n]+)/u", $text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
