<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\BaseController;
use app\common\exception\BizException;

/**
 * 内部后台基础控制器
 */
class Base extends BaseController
{
    /**
     * 成功响应 {code:0, msg, data, count}
     */
    protected function ok($data = [], string $msg = 'success', int $count = 0)
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data, 'count' => $count]);
    }

    /**
     * 按钮权限校验：非超管时要求 perms 中含标识
     * @param string $key 如 staff:create
     */
    protected function checkPerm(string $key): void
    {
        $auth = $this->request->auth;
        if (!empty($auth['is_super']) || in_array($key, (array) ($auth['perms'] ?? []), true)) {
            return;
        }
        throw new BizException('没有「' . $key . '」操作权限');
    }
}
