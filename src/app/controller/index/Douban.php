<?php

namespace app\controller\index;

use app\database\dao\BookDao;
use app\utils\BookManager\CoverManager;
use DOMDocument;
use DOMXPath;
use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\core\Logger;
use nova\framework\http\Response;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpException;
use nova\plugin\task\PoolManager;
use function nova\framework\dump;

/**
 * 豆瓣图书搜索服务
 *
 * 设计原则：简洁、实用、无过度设计
 * - 使用DOMDocument解析HTML（PHP内置，无需额外依赖）
 * - 简单的相似度排序（similar_text函数）
 * - 返回最佳匹配结果
 * - 随机UA和IP以规避反爬
 */
class Douban extends BaseController
{
    private const SEARCH_URL = 'https://www.douban.com/search';
    private const MIN_SIMILARITY = 0.6;


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
                'msg' => '搜索关键词不能为空'
            ]);
        }

        $results = $this->searchDouban($query);

        if (empty($results)) {
            return Response::asJson([
                'code' => 404,
                'msg' => '未找到匹配的书籍'
            ]);
        }

        return Response::asJson([
            'code' => 200,
            'data' => $results
        ]);
    }

    /**
     * 在豆瓣搜索书籍
     *
     * @param string $query 搜索关键词
     * @return array|null 书籍信息数组
     */
    private function searchDouban(string $query): ?array
    {

       $data =  Context::instance()->cache->get("search/$query");
       if (!empty($data)) {
           // 根据综合分数重新排序
           usort($data, function($a, $b) {
               return $this->calculateScore($b) <=> $this->calculateScore($a);
           });

           return $data;
       }

        // 发送HTTP请求（使用随机UA和IP）
        $client = HttpClient::init()
            ->timeout(300)
            ->gzip()
            ->setOption(CURLOPT_DNS_SERVERS, '223.5.5.5,223.6.6.6')
            ->setHeader('User-Agent', \app\utils\Douban::getRandomUserAgent())
            ->setHeader('X-Forwarded-For', \app\utils\Douban::getRandomIP())
            ->get();

        $response = $client->send(self::SEARCH_URL, [
            'cat' => '1001',  // 图书分类
            'q' => $query
        ]);

        if (!$response || $response->getHttpCode() !== 200) {
            return null;
        }

        // 解析HTML
        $html = $response->getBody();
        $books = $this->parseSearchResults($html, $query);

        if (empty($books)) {
            return null;
        }

        $doubans = [];


        PoolManager::instance()->runPool($books,function (array $book,int $index,PoolManager $manager)use ($query) {
            if (!empty($book['url'])) {
                $detailInfo = $this->fetchBookDetail($book['url']);
                if ($detailInfo) {
                    $detail  =  array_merge($book, $detailInfo);
                    Context::instance()->cache->set("search/$query/$index",$detail);
                }
            }
        },function (){});

        foreach (Context::instance()->cache->getAll("search/$query") as $key => $book) {
            $doubans[] = $book;
            Context::instance()->cache->delete($key);
        }

        // 根据综合分数重新排序
        usort($doubans, function($a, $b) {
            return $this->calculateScore($b) <=> $this->calculateScore($a);
        });

        Context::instance()->cache->set("search/$query",$doubans);

        return $doubans;
    }

    /**
     * 解析搜索结果页面
     *
     * @param string $html HTML内容
     * @param string $query 搜索关键词
     * @return array 书籍信息数组，按相似度排序
     */
    private function parseSearchResults(string $html, string $query): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // 查找所有搜索结果
        $results = $xpath->query("//div[@class='result-list']//div[@class='result']");

        $books = [];

        foreach ($results as $index => $result) {
            $book = $this->parseSearchItem($xpath, $result, $query);

            if ($book && $book['similarity'] >= self::MIN_SIMILARITY) {
                $books[] = $book;
            }
        }

        // 按相似度降序排序
        usort($books, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // 优先返回高相似度结果，最多10个
        return array_slice($books, 0, 10);
    }

    /**
     * 解析单个搜索结果项
     *
     * @param DOMXPath $xpath XPath对象
     * @param \DOMElement $result 结果元素
     * @param string $query 搜索关键词
     * @return array|null 书籍信息
     */
    private function parseSearchItem(DOMXPath $xpath, \DOMElement $result, string $query): ?array
    {
        // 提取标题和URL
        $titleNode = $xpath->query(".//div[@class='title']//h3/a", $result)->item(0);
        if (!$titleNode) {
            return null;
        }

        $title = trim($titleNode->textContent);
        $bookUrl = $titleNode->getAttribute('href');

        // 提取豆瓣ID
        $subjectId = $this->extractSubjectId($bookUrl);
        if (!$subjectId) {
            return null;
        }

        // 计算标题相似度
        $similarity = $this->calculateSimilarity($query, $title);

        // 提取出版信息
        $subjectCast = $xpath->query(".//div[@class='rating-info']//div[@class='subject-cast']", $result)->item(0);
        $info = $this->parsePublishInfo($subjectCast ? $subjectCast->textContent : '');

        // 提取封面
        $coverNode = $xpath->query(".//div[@class='pic']//img", $result)->item(0);
        $coverUrl = $coverNode?->getAttribute('src');

        // 提取评分
        $ratingNode = $xpath->query(".//div[@class='rating-info']//span[@class='rating_nums']", $result)->item(0);
        $rating = $ratingNode ? trim($ratingNode->textContent) : null;

        // 提取简介
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

    /**
     * 从URL中提取豆瓣subject ID
     *
     * @param string $url URL地址
     * @return string|null subject ID
     */
    private function extractSubjectId(string $url): ?string
    {
        // 处理重定向URL
        if (str_contains($url, 'link2')) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            $realUrl = urldecode($params['url'] ?? '');
        } else {
            $realUrl = $url;
        }

        // 提取subject ID
        if (preg_match('/subject\/(\d+)/', $realUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 解析出版信息字符串
     * 格式：作者 / 出版社 / 出版年 / 价格
     *
     * @param string $text 出版信息文本
     * @return array 解析后的信息数组
     */
    private function parsePublishInfo(string $text): array
    {
        $parts = array_map('trim', explode('/', $text));

        $info = [
            'author' => $parts[0] ?? '',
            'publisher' => '',
            'year' => ''
        ];

        // 倒数第二个通常是出版社
        if (count($parts) > 2) {
            $info['publisher'] = $parts[count($parts) - 2];
        }

        // 查找4位数字的年份
        foreach ($parts as $part) {
            if (preg_match('/^\d{4}$/', $part)) {
                $info['year'] = $part;
                break;
            }
        }

        return $info;
    }

    /**
     * 计算字符串相似度
     *
     * @param string $str1 字符串1
     * @param string $str2 字符串2
     * @return float 相似度 (0-1)
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // 标准化：转小写、去空格
        $s1 = str_replace(' ', '', mb_strtolower($str1));
        $s2 = str_replace(' ', '', mb_strtolower($str2));

        // 使用PHP内置的相似度函数
        similar_text($s1, $s2, $percent);

        return $percent / 100;
    }

    /**
     * 计算书籍综合评分
     * 
     * @param array $book 书籍数据
     * @return float 综合分数 (0-100)
     */
    private function calculateScore(array $book): float
    {
        // 相似度权重 70%
        $similarityScore = ($book['similarity'] ?? 0) * 70;

        // 字段完整度权重 30%
        $completenessScore = $this->calculateCompleteness($book) * 30;

        return $similarityScore + $completenessScore;
    }

    /**
     * 计算字段完整度
     * 
     * @param array $book 书籍数据
     * @return float 完整度 (0-1)
     */
    private function calculateCompleteness(array $book): float
    {
        // 关键字段权重分配（title相似度已在similarity中占70%）
        $fields = [
            'author' => 0.35,      // 作者最重要
            'full_intro' => 0.25,  // 完整简介其次
            'cover_url' => 0.15,   // 封面图
            'rating' => 0.15,      // 评分
            'isbn' => 0.05,        // ISBN
            'publisher' => 0.03,   // 出版社
            'year' => 0.02,        // 出版年份
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
     * 获取图书详情页信息
     *
     * @param string $url 图书详情页URL
     * @return array|null 详细信息
     */
    private function fetchBookDetail(string $url): ?array
    {
        $client = HttpClient::init()
            ->timeout(300)
            ->gzip()
            ->setOption(CURLOPT_DNS_SERVERS, '223.5.5.5,223.6.6.6')
            ->setHeader('User-Agent', \app\utils\Douban::getRandomUserAgent())
            ->setHeader('X-Forwarded-For', \app\utils\Douban::getRandomIP())
            ->get();

        try {
            $response = $client->send($url);
        } catch (HttpException $e) {
            Logger::error($e->getMessage());
            return null;
        }

        if (!$response || $response->getHttpCode() !== 200) {
            return null;
        }

        $html = $response->getBody();
        return $this->parseBookDetail($html);
    }

    /**
     * 解析图书详情页
     *
     * @param string $html HTML内容
     * @return array 详细信息
     */
    private function parseBookDetail(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // 获取info区域的文本内容并清理（去空行和每行首尾空白）
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
        // 提取完整简介
        $introNode = $xpath->query("//div[@id='link-report']//div[@class='intro']")->item(0);
        if ($introNode) {
            $detail['full_intro'] = trim($introNode->textContent);
        }

        // 提取标签
        $tagNodes = $xpath->query("//a[@class='tag']");
        $tags = [];
        foreach ($tagNodes as $tagNode) {
            $tags[] = trim($tagNode->textContent);
        }
        $detail['tags'] = array_unique($tags);

        return array_filter($detail); // 移除空值
    }

    /**
     * 从文本中提取字段值
     *
     * @param string $text 文本内容
     * @param string $field 字段名
     * @return string|null 字段值
     */
    private function extractField(string $text, string $field): ?string
    {
        if (preg_match("/{$field}[：:]\s*([^\n]+)/u", $text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    public function proxy(string $uri):Response{
        $file = \app\utils\Douban::download($uri);
        return Response::asStatic($file);
    }
    public function webdav(string $filename):Response
    {
        $filename = rawurldecode($filename);
       $book = BookDao::getInstance()->getByFileName($filename);
       if (empty($book)){
           return Response::asText('404 not found');
       }
       if (empty($book->coverUrl)){
           return Response::asStatic(CoverManager::getInstance()->getCover($filename));
       }
        $file = \app\utils\Douban::download($book->coverUrl);
        return Response::asStatic($file);
    }
}