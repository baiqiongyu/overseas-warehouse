<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\exception\BizException;
use app\common\model\Admin;
use app\common\model\Menu;
use app\common\model\Role;
use app\common\service\TokenService;
use think\facade\Cache;
use think\facade\Db;

/**
 * 内部后台 - 登录 / 登出 / 当前用户与菜单
 */
class Auth extends Base
{
    const MAX_FAILS   = 5;
    const LOCK_SECOND = 600;

    /**
     * 员工登录
     * POST /admin/auth/login  {email, password}
     */
    public function login()
    {
        $email    = strtolower(trim((string) $this->request->param('email', '')));
        $password = (string) $this->request->param('password', '');
        if ($email === '' || $password === '') {
            throw new BizException('请输入邮箱和密码');
        }

        // 登录失败锁定
        $failKey = 'admin_login_fail_' . md5($email);
        if ((int) Cache::get($failKey, 0) >= self::MAX_FAILS) {
            throw new BizException('失败次数过多，账号已锁定 ' . (self::LOCK_SECOND / 60) . ' 分钟');
        }

        $admin = Admin::where('email', $email)
            ->where('is_deleted', 0)
            ->whereNull('customer_id') // 员工账号
            ->find();
        if (empty($admin) || !password_verify($password, (string) $admin->password)) {
            Cache::set($failKey, (int) Cache::get($failKey, 0) + 1, self::LOCK_SECOND);
            throw new BizException('邮箱或密码错误');
        }
        if ((int) $admin->status !== 1) {
            throw new BizException('账号已停用，请联系管理员');
        }

        Cache::delete($failKey);
        $admin->last_login_time = time();
        $admin->last_login_ip   = $this->request->ip();
        $admin->save();

        $token = TokenService::create((int) $admin->id, 0);
        return $this->ok([
            'token' => $token,
            'user'  => ['id' => (int) $admin->id, 'realname' => $admin->realname, 'email' => $admin->email],
        ], '登录成功');
    }

    /**
     * 登出
     * POST /admin/auth/logout
     */
    public function logout()
    {
        TokenService::delete((string) $this->request->token);
        return $this->ok([], '已退出登录');
    }

    /**
     * 当前用户信息 + 可见菜单树（登录后渲染侧栏）
     * GET /admin/auth/me
     */
    public function me()
    {
        $auth = $this->request->auth;

        // 超管：全量启用菜单；普通角色：按授权
        if (!empty($auth['is_super'])) {
            $menus = Menu::where('status', 1)->where('is_deleted', 0)->order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        } else {
            $menuIds = Db::name('role_menu')->where('role_id', (int) $auth['role_id'])->column('menu_id');
            $menus   = empty($menuIds) ? [] : Menu::whereIn('id', $menuIds)
                ->where('status', 1)->where('is_deleted', 0)
                ->order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        }

        return $this->ok([
            'user'  => [
                'admin_id'  => $auth['admin_id'],
                'email'     => $auth['email'],
                'realname'  => $auth['realname'],
                'role_id'   => $auth['role_id'],
                'role_name' => $auth['role_name'],
                'is_super'  => $auth['is_super'],
            ],
            'perms' => $auth['is_super'] ? ['*'] : array_values(array_filter((array) $auth['perms'])),
            'menus' => $this->tree($menus, 0),
        ]);
    }

    /** 菜单树组装（目录 + 页面；按钮随页面走，前端用 perms 控制） */
    private function tree(array $rows, int $pid): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r['pid'] !== $pid) {
                continue;
            }
            if ((int) $r['type'] === Menu::TYPE_BUTTON) {
                continue; // 侧栏不展示按钮
            }
            $node = [
                'id'    => (int) $r['id'],
                'pid'   => (int) $r['pid'],
                'type'  => (int) $r['type'],
                'title' => $r['title'],
                'path'  => $r['path'],
                'icon'  => $r['icon'],
            ];
            $children = $this->tree($rows, (int) $r['id']);
            if ($children) {
                $node['children'] = $children;
            }
            $out[] = $node;
        }
        return $out;
    }
}
