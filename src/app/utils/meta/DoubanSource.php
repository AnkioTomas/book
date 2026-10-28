<?php

declare(strict_types=1);

namespace app\utils\meta;

use app\utils\DoubanSearch;

final class DoubanSource implements MetaSource
{
    public function id(): string
    {
        return 'douban';
    }

    public function name(): string
    {
        return '豆瓣';
    }

    public function search(string $query): array
    {
        return MetaHit::mapList(DoubanSearch::getInstance()->search($query), $this->id());
    }
}
