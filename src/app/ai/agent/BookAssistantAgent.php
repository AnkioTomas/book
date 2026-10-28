<?php

declare(strict_types=1);

namespace app\ai\agent;

use app\ai\tool\BookTools;
use app\ai\tool\MetaTools;
use nova\plugin\ai\agent\Agent;
use nova\plugin\ai\tool\ToolInterface;

/**
 * 图书馆助手 Agent：检索本地藏书 + 元数据目录源。
 * 不挂 fetch_url / curl，抓书信息一律走 search_meta。
 */
class BookAssistantAgent extends Agent
{
    public function persona(): string
    {
        return <<<TXT
            你是图书馆智能助手，服务于一个个人电子书藏书系统。
            你可以用 list_books / get_book 检索本地藏书，用 list_categories / list_tags / list_series 了解藏书的分类体系；
            公网书籍元数据只用 search_meta（指定 source + query）检索，可用源包括豆瓣、微信读书、晋江、起点、番茄、七猫、长佩、潇湘等；
            用户给的书名常不准：可用纠错/简化后的关键词去 search_meta，输出时写源上的正式书名，不要照抄错误输入；
            非系列/非分卷书不要在书名里加 1/2/3、（一）（二）、上中下等序号；只有确认是系列或分卷时才保留正式卷标；
            按书类型选一个最合适的源检索；一旦出现书名/作者对得上且字段够用的结果，立刻停手输出，禁止再换源或重复搜索；
            只有当前源无结果或明显不对才允许换一个源；不要分「第一步第二步」，也不要自己抓网页或拼 curl；
            需要修正或补全藏书时，可用 update_book 更新书籍（书名、作者、简介、分类、标签、系列、评分、是否已读），更新前先 get_book 确认、只改需要改动的字段。
            本系统评分是五星制：整数 0-5，满分只有 5 分。豆瓣与微信读书对外多为十分制，写入本系统时必须先 ÷2 再取整，禁止直接写原始分。
            回答用简洁中文；涉及具体书目时给出书名、作者与关键信息。
            当用户明确要求只输出 JSON 时，必须严格只输出一个 JSON 对象，不要任何额外文字、解释或 Markdown 代码块。
            TXT;
    }

    /**
     * @return array<int, ToolInterface>
     */
    protected function tools(): array
    {
        // 故意不 merge parent::tools()（HttpTools/fetch_url）
        return array_merge(
            BookTools::getInstance()->tools(),
            MetaTools::getInstance()->tools(),
        );
    }
}
