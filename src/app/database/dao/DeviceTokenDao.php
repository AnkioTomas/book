<?php

declare(strict_types=1);

namespace app\database\dao;

use app\database\model\DeviceTokenModel;
use nova\plugin\login\db\Dao\UserDao;
use nova\plugin\login\db\Model\UserModel;
use nova\plugin\orm\object\Dao;

class DeviceTokenDao extends Dao
{
    public const PREFIX = 'bk_';
    /** 明文形如 bk_XXXXXXXX（8 位 base62） */
    public const SECRET_LEN = 8;
    private const CHARSET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function findByHash(string $hash): ?DeviceTokenModel
    {
        /** @var DeviceTokenModel|null $row */
        $row = $this->find(null, ['token_hash' => $hash]);
        return $row;
    }

    /**
     * @return DeviceTokenModel[]
     */
    public function listByUser(int $userId): array
    {
        return $this->select()
            ->where(['user_id' => $userId])
            ->orderBy('created_at', 'DESC')
            ->commit();
    }

    public function touchLastUsed(DeviceTokenModel $token): void
    {
        $now = time();
        // 60s 内不刷写，避免每次翻页打库
        if ($token->last_used_at > 0 && ($now - $token->last_used_at) < 60) {
            return;
        }
        $token->last_used_at = $now;
        $this->updateModel($token);
    }

    /**
     * 明文 token 只在创建响应里出现一次。格式：bk_ + 8 位。
     */
    public function createToken(int $userId, string $name, int $expiresAt = 0): array
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'KOReader';
        }

        $plain = '';
        $hash = '';
        for ($i = 0; $i < 8; $i++) {
            $plain = self::PREFIX . $this->randomSecret(self::SECRET_LEN);
            $hash = hash('sha256', $plain);
            if ($this->findByHash($hash) === null) {
                break;
            }
            $plain = '';
        }
        if ($plain === '') {
            throw new \RuntimeException('无法生成唯一令牌');
        }

        $token = new DeviceTokenModel();
        $token->user_id = $userId;
        $token->name = mb_substr($name, 0, 64);
        $token->token_hash = $hash;
        // 短令牌不能存任何 secret 字符，否则列表等于明文
        $token->token_prefix = self::PREFIX;
        $token->created_at = time();
        $token->last_used_at = 0;
        $token->expires_at = $expiresAt;
        $token->id = $this->insertModel($token);

        return [
            'id' => $token->id,
            'name' => $token->name,
            'token' => $plain,
            'created_at' => $token->created_at,
            'expires_at' => $token->expires_at,
        ];
    }

    private function randomSecret(int $len): string
    {
        $max = strlen(self::CHARSET);
        $out = '';
        while (strlen($out) < $len) {
            foreach (str_split(random_bytes($len)) as $byte) {
                $n = ord($byte);
                // 拒绝采样，消除 256 % 62 偏差
                if ($n >= intdiv(256, $max) * $max) {
                    continue;
                }
                $out .= self::CHARSET[$n % $max];
                if (strlen($out) >= $len) {
                    break;
                }
            }
        }
        return $out;
    }

    public function authenticate(string $plain): ?UserModel
    {
        if ($plain === '' || !str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        $row = $this->findByHash(hash('sha256', $plain));
        if ($row === null) {
            return null;
        }
        if ($row->expires_at > 0 && $row->expires_at < time()) {
            return null;
        }

        $user = UserDao::getInstance()->id($row->user_id);
        if (!$user instanceof UserModel) {
            return null;
        }

        $this->touchLastUsed($row);
        return $user;
    }
}
