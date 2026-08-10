<?php

declare(strict_types=1);

namespace app\database\model;

use nova\plugin\orm\object\Model;

/**
 * KOReader / 第三方客户端长期凭证。
 * 明文 token 只在创建时返回一次，库里只存 sha256。
 */
class DeviceTokenModel extends Model
{
    public int $user_id = 0;
    public string $name = '';
    public string $token_hash = '';
    public string $token_prefix = '';
    public int $created_at = 0;
    public int $last_used_at = 0;
    /** 0 = 永不过期 */
    public int $expires_at = 0;

    public function getUnique(): array
    {
        return ['token_hash'];
    }

    public function getSchemaVersion(): int
    {
        return 1;
    }
}
