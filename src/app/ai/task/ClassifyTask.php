<?php

declare(strict_types=1);

namespace app\ai\task;

use app\ai\agent\BookAssistantAgent;
use nova\plugin\ai\agent\Agent;
use nova\plugin\ai\task\AiTask;

/**
 * 分类任务：根据书名、作者、简介判断图书分类和标签。
 *
 * 保留原有标签中的「已读」状态，AI 生成的标签与之合并。
 */
class ClassifyTask extends AiTask
{
    private const array ALLOW = ['favorite', 'category'];

    private string $existingCategory = '';

    protected function agent(): Agent
    {
        return BookAssistantAgent::getInstance();
    }

    protected function allowedFields(): array
    {
        return self::ALLOW;
    }

    protected function postProcess(array $out): array
    {
        if (isset($out['favorite']) && !in_array($out['favorite'], MetadataFillTask::CATEGORIES, true)) {
            unset($out['favorite']);
        }

        if (isset($out['category'])) {
            $newTags = array_filter(array_map('trim', explode("\n", $out['category'])));
            $oldTags = array_filter(array_map('trim', explode("\n", $this->existingCategory)));
            $preserved = array_intersect($oldTags, ['已读']);
            $merged = array_unique(array_merge($preserved, $newTags));
            $out['category'] = implode("\n", $merged);
        }

        return $out;
    }

    protected function summarize(array $out): string
    {
        $parts = [];
        if (isset($out['favorite'])) {
            $parts[] = '分类:' . $out['favorite'];
        }
        if (isset($out['category'])) {
            $parts[] = '标签:' . implode(',', array_slice(explode("\n", $out['category']), 0, 3));
        }
        return $parts === [] ? '已分类' : implode(' / ', $parts);
    }

    /**
     * @param  string                    $existingCategory 当前标签（换行分隔），用于保留「已读」等状态标签
     * @return array<string, mixed>|null
     */
    public function classify(
        string $bookName,
        string $author = '',
        string $description = '',
        string $existingCategory = '',
        ?callable $onProgress = null,
    ): ?array {
        if ($bookName === '') {
            return null;
        }

        $this->existingCategory = $existingCategory;

        $categories = implode('/', MetadataFillTask::CATEGORIES);
        $prompt = "请根据以下信息判断这本书最合适的分类和标签：\n"
            . "书名：{$bookName}\n"
            . ($author !== '' ? "作者：{$author}\n" : '')
            . ($description !== '' ? "简介：{$description}\n" : '')
            . "可以用 search_douban 检索豆瓣获取标签信息。"
            . "最终只输出一个 JSON 对象，不要任何额外文字，字段为："
            . "favorite(图书分类，必须从以下固定分类中选且只能选一个：{$categories}), "
            . "category(标签，多个用换行符\\n分隔，例如 科幻\\n经典；不要包含「已读」等阅读状态标签)。";

        return $this->execute($prompt, $onProgress);
    }
}
