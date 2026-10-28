<?php
declare(strict_types=1);

namespace app\common\middleware;

use app\common\model\Admin;
use app\common\model\Menu;
use app\common\model\Role;
use app\common\service\TokenService;

/**
 * 内部后台鉴权中间件
 *
 * 与 AuthToken 的区别：只允许员工账号（customer_id 为空），
 * 并把角色名 / 是否超管 / 权限标识集合挂到 $request->auth。
 */
class AdminAuth
{
    public function handle($request, \Closure $next)
    {
        $token = trim((string) $request->header('token', ''));
        if ($token === '') {
            $token = trim((string) $request->param('token', ''));
        }

        $payload = $token !== '' ? TokenService::get($token) : null;
        if (empty($payload)) {
            return json(['code' => 401, 'msg' => '请先登录或登录已过期', 'data' => [], 'count' => 0]);
        }

        // 只允许员工账号进入后台
        if ((int) ($payload['customer_id'] ?? 0) > 0) {
            return json(['code' => 403, 'msg' => '员工账号才能进入管理后台', 'data' => [], 'count' => 0]);
        }

        $admin = Admin::where('id', (int) $payload['admin_id'])
            ->where('is_deleted', 0)
            ->where('status', 1)
            ->find();
        if (empty($admin)) {
            return json(['code' => 401, 'msg' => '账号不存在或已停用', 'data' => [], 'count' => 0]);
        }

        $role   = $admin->role_id ? Role::where('id', $admin->role_id)->where('status', 1)->where('is_deleted', 0)->find() : null;
        $isSuper = $role && (int) $role->is_super === 1;

        // 权限标识集合 = 角色绑定的全部菜单 path（含按钮标识）
        $perms = [];
        if (!$isSuper && $role) {
            $perms = Menu::alias('m')
                ->join('role_menu rm', 'rm.menu_id = m.id')
                ->where('rm.role_id', $role->id)
                ->where('m.status', 1)
                ->where('m.is_deleted', 0)
                ->column('m.path');
        }

        $request->auth = [
            'admin_id'  => (int) $admin->id,
            'email'     => (string) $admin->email,
            'realname'  => (string) $admin->realname,
            'role_id'   => $role ? (int) $role->id : 0,
            'role_name' => $role ? (string) $role->name : '',
            'is_super'  => $isSuper,
            'perms'     => $perms,
        ];
        $request->token = $token;

        return $next($request);
    }
}
