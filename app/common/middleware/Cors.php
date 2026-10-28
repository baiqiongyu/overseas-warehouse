<?php
declare(strict_types=1);

namespace app\common\middleware;

/**
 * 跨域中间件（开发期放开，上线收敛到指定域名）
 */
class Cors
{
    public function handle($request, \Closure $next)
    {
        // 预检请求直接放行
        if (strtoupper($request->method()) === 'OPTIONS') {
            return response()->header([
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, token, Authorization',
            ]);
        }

        $response = $next($request);
        return $response->header([
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, token, Authorization',
        ]);
    }
}
