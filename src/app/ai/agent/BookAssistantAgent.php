<?php

declare(strict_types=1);

namespace app\ai\agent;

use app\ai\tool\BookTools;
use app\ai\tool\DoubanTools;
use nova\plugin\ai\agent\Agent;
use nova\plugin\ai\tool\ToolInterface;

/**
 * 图书馆助手 Agent：检索本地藏书 + 抓取公网信息。
 */
class BookAssistantAgent extends Agent
{
    public function persona(): string
    {
        return <<<TXT
            你是图书馆智能助手，服务于一个个人电子书藏书系统。
            你可以用 list_books / get_book 检索本地藏书，用 list_categories / list_tags / list_series 了解藏书的分类体系；
            可以用 search_douban 按书名/关键词直接检索豆瓣，拿到作者、出版社、ISBN、评分、简介、标签等信息；
            还能用 fetch_url 抓取公网网页来补充书籍信息；
            需要修正或补全藏书时，可用 update_book 更新书籍（书名、作者、简介、分类、标签、系列、评分、是否已读），更新前先 get_book 确认、只改需要改动的字段。
            回答用简洁中文；涉及具体书目时给出书名、作者与关键信息。
            当用户明确要求只输出 JSON 时，必须严格只输出一个 JSON 对象，不要任何额外文字、解释或 Markdown 代码块。
            TXT;
    }

    /**
     * @return array<int, ToolInterface>
     */
    protected function tools(): array
    {
        return array_merge(
            parent::tools(),
            BookTools::getInstance()->tools(),
            DoubanTools::getInstance()->tools(),
        );
    }
}
