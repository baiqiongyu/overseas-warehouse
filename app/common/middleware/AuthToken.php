<?php
declare(strict_types=1);

namespace app\common\middleware;

use app\common\service\TokenService;

/**
 * 登录鉴权中间件：校验 header 中的 token，通过后把登录态挂到 $request->auth
 */
class AuthToken
{
    public function handle($request, \Closure $next)
    {
        $token = trim((string) $request->header('token', ''));
        if ($token === '') {
            $token = trim((string) $request->param('token', ''));
        }

        $payload = $token !== '' ? TokenService::get($token) : null;
        if (empty($payload)) {
            return json([
                'code'  => 401,
                'msg'   => '请先登录或登录已过期',
                'data'  => [],
                'count' => 0,
            ]);
        }

        // 挂载登录态，控制器内通过 $request->auth / $request->token 获取
        $request->auth  = $payload;
        $request->token = $token;

        return $next($request);
    }
}
