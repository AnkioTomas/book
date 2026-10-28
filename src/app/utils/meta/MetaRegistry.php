<?php

declare(strict_types=1);

namespace app\utils\meta;

use nova\framework\core\Instance;

/**
 * 元数据搜索源注册表（顺序即 UI 左栏顺序）。
 */
final class MetaRegistry extends Instance
{
    /** @var list<MetaSource>|null */
    private ?array $sources = null;

    /**
     * 真实可检索源（不含前端 AI 入口）。
     *
     * @return list<array{id: string, name: string}>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->all() as $src) {
            $out[] = ['id' => $src->id(), 'name' => $src->name()];
        }
        return $out;
    }

    /** @return list<array{id: string, name: string}> */
    public function listSources(): array
    {
        $out = $this->catalog();
        // AI 由前端 SSE 处理，仍出现在列表里方便统一入口
        $out[] = ['id' => 'ai', 'name' => 'AI 填充'];
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $sourceId, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        foreach ($this->all() as $src) {
            if ($src->id() === $sourceId) {
                return $src->search($query);
            }
        }
        throw new \InvalidArgumentException('未知数据源: ' . $sourceId);
    }

    /** @return list<MetaSource> */
    private function all(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }
        $this->sources = [
            new DoubanSource(),
            new WereadSource(),
            new JjwxcSource(),
            new QidianSource(),
            new FanqieSource(),
            new QimaoSource(),
            new ChangpeiSource(),
            new XiaoxiangSource(),
        ];
        return $this->sources;
    }
}
