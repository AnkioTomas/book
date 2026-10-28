<?php

declare(strict_types=1);

namespace app\utils\meta;

/**
 * 元数据搜索源。
 */
interface MetaSource
{
    public function id(): string;

    public function name(): string;

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array;
}
