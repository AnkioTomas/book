<?php

declare(strict_types=1);

namespace app\ai;

use app\ai\agent\BookAssistantAgent;
use nova\framework\core\Logger;

/**
 * 书籍元数据解析器：给定书名/作者，让 AI 检索并挑选最匹配的元数据，返回白名单字段。
 *
 * 从 Book 控制器抽离，供 SSE 预填（aiFill）与后台批量识别任务（aiIdentify）共用，
 * 避免逻辑重复，也让后台 task 闭包可直接复用（控制器实例不可序列化）。
 */
class BookMetadataResolver
{
    /** AI 返回字段白名单 */
    private const array ALLOW = [
        'bookName', 'author', 'description', 'publisher', 'publishYear',
        'isbn', 'pages', 'price', 'coverUrl', 'rate', 'favorite', 'category',
    ];

    /** 图书分类固定枚举：favorite 字段只能取其中之一，否则视为无效不落库。 */
    public const array CATEGORIES = [
        '计算机', '数学', '自然科学', '工程技术', '医学', '经济', '管理',
        '法律', '政治', '历史', '哲学', '心理学', '文学', '艺术', '语言学习',
        '教育', '生活', '传记', '儿童', '漫画', '杂志', '工具书', '其他',
    ];

    /** 工具调用 -> 进度提示文案 */
    private const array TOOL_HINTS = [
        'search_douban' => '正在检索豆瓣…',
        'fetch_url'     => '正在抓取网页补充信息…',
        'get_book'      => '正在读取本地书籍…',
        'list_books'    => '正在检索本地藏书…',
    ];

    /**
     * 让 AI 检索并挑选最匹配的元数据，返回白名单后的字段（无结果/异常返回 null）。
     *
     * @param  callable|null            $onProgress function(string $msg): void
     * @return array<string,mixed>|null
     */
    public function resolve(string $bookName, string $author, ?callable $onProgress = null): ?array
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

        try {
            $last = '';
            $answer = BookAssistantAgent::getInstance()->run($prompt, [
                'onChunk' => function (string $text, string $type) use ($onProgress, &$last): void {
                    if ($onProgress === null) {
                        return;
                    }
                    $msg = match ($type) {
                        'tool_call'   => self::TOOL_HINTS[$text] ?? ('正在调用 ' . $text . '…'),
                        'tool_result' => '已获取数据，分析中…',
                        'thinking'    => 'AI 思考中…',
                        default       => '',
                    };
                    if ($msg !== '' && $msg !== $last) {
                        $last = $msg;
                        $onProgress($msg);
                    }
                },
            ]);
        } catch (\Throwable $e) {
            // 不再静默吞掉：AI 调用失败时把真实原因暴露出来，否则只看到「返回 null」无从排查。
            Logger::error('[BookMetadataResolver] AI 调用异常：' . $e->getMessage());
            return null;
        }

        // run() 返回 null 多为「AI 提供商未配置/解析失败」——此时根本没调用模型，必须可见。
        if ($answer === null || trim($answer) === '') {
            Logger::warning('[BookMetadataResolver] AI 无返回（请检查 AI 提供商是否已正确配置）：' . $bookName);
            return null;
        }

        $data = $this->extractJsonObject($answer);
        if ($data === null) {
            Logger::warning('[BookMetadataResolver] AI 返回内容无法解析出 JSON：' . $answer);
            return null;
        }

        $out = [];
        foreach (self::ALLOW as $field) {
            $value = $data[$field] ?? '';
            // 标签可能以数组返回，统一成换行分隔的字符串
            if (is_array($value)) {
                $value = implode("\n", array_map(static fn ($v): string => trim((string)$v), $value));
            }
            $value = trim((string)$value);
            if ($value !== '') {
                $out[$field] = $value;
            }
        }

        // 分类只接受固定枚举，AI 给了枚举外的值就丢弃，避免污染分类体系。
        if (isset($out['favorite']) && !in_array($out['favorite'], self::CATEGORIES, true)) {
            unset($out['favorite']);
        }

        return $out === [] ? null : $out;
    }

    /**
     * 从可能夹杂说明文字/代码块的文本里提取第一个 JSON 对象。
     *
     * @return array<string,mixed>|null
     */
    private function extractJsonObject(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }
}
