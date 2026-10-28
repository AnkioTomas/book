<?php

declare(strict_types=1);

namespace app\utils\meta;

use app\utils\WereadSearch;

final class WereadSource implements MetaSource
{
    public function id(): string
    {
        return 'weread';
    }

    public function name(): string
    {
        return '微信读书';
    }

    public function search(string $query): array
    {
        return MetaHit::mapList(WereadSearch::getInstance()->search($query), $this->id());
    }
}
