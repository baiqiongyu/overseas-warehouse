<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Cache;

/**
 * 登录令牌服务
 *
 * 本地开发阶段使用文件缓存存储 token（key: token:{token}），
 * 上线后把 cache 驱动切到 Redis 即可，业务代码无需改动。
 */
class TokenService
{
    /** 有效期：7 天 */
    const EXPIRE = 604800;

    const CACHE_PREFIX = 'token:';

    /**
     * 生成令牌
     * @param int $adminId    账号 ID
     * @param int $customerId 货主 ID
     */
    public static function create(int $adminId, int $customerId): string
    {
        $token = bin2hex(random_bytes(20));
        Cache::set(self::CACHE_PREFIX . $token, [
            'admin_id'    => $adminId,
            'customer_id' => $customerId,
            'login_time'  => time(),
        ], self::EXPIRE);
        return $token;
    }

    /** 读取令牌 payload，无效返回 null */
    public static function get(string $token): ?array
    {
        $payload = Cache::get(self::CACHE_PREFIX . $token);
        return is_array($payload) ? $payload : null;
    }

    /** 注销令牌 */
    public static function delete(string $token): void
    {
        Cache::delete(self::CACHE_PREFIX . $token);
    }
}
