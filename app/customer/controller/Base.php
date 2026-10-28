<?php
declare(strict_types=1);

namespace app\customer\controller;

use app\BaseController;

/**
 * 货主门户基础控制器：统一成功响应
 */
class Base extends BaseController
{
    /**
     * 成功响应 {code:0, msg, data, count}
     */
    protected function ok($data = [], string $msg = 'success', int $count = 0)
    {
        return json([
            'code'  => 0,
            'msg'   => $msg,
            'data'  => $data,
            'count' => $count,
        ]);
    }
}
