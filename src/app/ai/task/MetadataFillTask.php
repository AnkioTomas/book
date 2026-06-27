<?php

declare(strict_types=1);

namespace app\ai\task;

use app\ai\agent\BookAssistantAgent;
use nova\plugin\ai\agent\Agent;
use nova\plugin\ai\task\AiTask;

/**
 * 元数据填充任务：给定书名/作者，补全全部元数据（描述、出版信息、封面、评分、分类、标签）。
 */
class MetadataFillTask extends AiTask
{
    private const array ALLOW = [
        'bookName', 'author', 'description', 'publisher', 'publishYear',
        'isbn', 'pages', 'price', 'coverUrl', 'rate', 'favorite', 'category',
    ];

    /** 图书分类固定枚举 */
    public const array CATEGORIES = [
        '计算机', '数学', '自然科学', '工程技术', '医学', '经济', '管理',
        '法律', '政治', '历史', '哲学', '心理学', '文学', '艺术', '语言学习',
        '教育', '生活', '传记', '儿童', '漫画', '杂志', '工具书', '其他',
    ];

    private const array TOOL_LABELS = [
        'search_douban'   => '检索豆瓣',
        'fetch_url'       => '抓取网页',
        'get_book'        => '读取本地书籍',
        'list_books'      => '检索本地藏书',
        'list_categories' => '读取分类体系',
        'list_tags'       => '读取标签体系',
        'list_series'     => '读取系列体系',
    ];

    protected function agent(): Agent
    {
        return BookAssistantAgent::getInstance();
    }

    protected function allowedFields(): array
    {
        return self::ALLOW;
    }

    protected function toolLabels(): array
    {
        return self::TOOL_LABELS;
    }

    protected function postProcess(array $out): array
    {
        if (isset($out['favorite']) && !in_array($out['favorite'], self::CATEGORIES, true)) {
            unset($out['favorite']);
        }
        return $out;
    }

    protected function summarize(array $out): string
    {
        $parts = [];
        if (isset($out['bookName'])) {
            $parts[] = '《' . $out['bookName'] . '》';
        }
        if (isset($out['author'])) {
            $parts[] = $out['author'];
        }
        if (isset($out['favorite'])) {
            $parts[] = '分类:' . $out['favorite'];
        }
        if (isset($out['rate'])) {
            $parts[] = '评分:' . $out['rate'];
        }
        return $parts === [] ? '已获取元数据' : implode(' / ', $parts);
    }

    /** @return array<string, mixed>|null */
    public function fill(string $bookName, string $author = '', ?callable $onProgress = null): ?array
    {
        if ($bookName === '') {
            return null;
        }

        $prompt = "请为下面这本书检索并挑选最准确的元数据：\n"
            . "书名：{$bookName}\n"
            . ($author !== '' ? "作者：{$author}\n" : '')
            . "用 search_douban 检索，必要时用 fetch_url 补全，在多个候选里挑选最匹配的一条。"
            . "最终只输出一个 JSON 对象，不要任何额外文字、解释或 Markdown 代码块，字段为："
            . "bookName(书名), author(作者), description(简介), publisher(出版社), publishYear(出版年), "
            . "isbn, pages(页数), price(价格), coverUrl(封面图URL), rate(0-5 整数评分), "
            . "favorite(图书分类，必须从以下固定分类中选且只能选一个：" . implode('/', self::CATEGORIES) . "), "
            . "category(标签，多个用换行符\\n分隔，例如 科幻\\n经典；不要包含「已读」等阅读状态)。"
            . "请根据书籍内容自动判断分类与标签。未知字段填空字符串。";

        return $this->execute($prompt, $onProgress);
    }
}
