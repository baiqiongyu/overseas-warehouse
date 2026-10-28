<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\exception\BizException;
use app\common\model\Admin;
use app\common\model\Menu;
use app\common\model\Role;
use think\facade\Db;

/**
 * 角色管理
 *
 * GET  /admin/role/list   分页
 * GET  /admin/role/all    全量（员工下拉用）
 * GET  /admin/role/menus  ?id= 该角色已授权 menu_id 集合
 * POST /admin/role/create {name, remark?, menu_ids:[]}
 * POST /admin/role/update {id, name?, remark?, menu_ids?[]}
 * POST /admin/role/status {id, status}
 * POST /admin/role/delete {id}
 */
class RoleController extends Base
{
    public function list()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));

        $query = Role::where('is_deleted', 0);
        $total = (clone $query)->count();
        $rows = $query->order('id', 'asc')->page($page, $limit)->select()->toArray();
        foreach ($rows as &$r) {
            $r['admin_count'] = Admin::where('role_id', $r['id'])->where('is_deleted', 0)->count();
            $r['menu_count']  = Db::name('role_menu')->where('role_id', $r['id'])->count();
        }
        unset($r);

        return $this->ok($rows, 'success', $total);
    }

    public function all()
    {
        $rows = Role::where('is_deleted', 0)->where('status', 1)->order('id', 'asc')
            ->field('id, name, is_super')->select()->toArray();
        return $this->ok($rows);
    }

    public function menus()
    {
        $id = (int) $this->request->param('id', 0);
        $role = Role::where('id', $id)->where('is_deleted', 0)->find();
        if (empty($role)) {
            throw new BizException('角色不存在');
        }
        $menuIds = Db::name('role_menu')->where('role_id', $id)->column('menu_id');
        return $this->ok(['is_super' => (int) $role->is_super, 'menu_ids' => array_map('intval', $menuIds)]);
    }

    public function create()
    {
        $this->checkPerm('role:create');
        $name = trim((string) $this->request->param('name', ''));
        if ($name === '') {
            throw new BizException('请填写角色名称');
        }
        if (Role::where('name', $name)->where('is_deleted', 0)->find()) {
            throw new BizException('角色名称已存在');
        }

        Db::startTrans();
        try {
            $role = Role::create([
                'name'       => $name,
                'remark'     => trim((string) $this->request->param('remark', '')),
                'creator_id' => (int) $this->request->auth['admin_id'],
            ]);
            $this->saveMenus((int) $role->id, (array) $this->request->param('menu_ids', []));
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return $this->ok(['id' => (int) $role->id], '角色已创建');
    }

    public function update()
    {
        $this->checkPerm('role:update');
        $role = $this->findRole((int) $this->request->param('id', 0));

        $name = trim((string) $this->request->param('name', ''));
        if ($name !== '' && $name !== $role->name) {
            if (Role::where('name', $name)->where('is_deleted', 0)->where('id', '<>', $role->id)->find()) {
                throw new BizException('角色名称已存在');
            }
            $role->name = $name;
        }
        if ($this->request->param('remark') !== null) {
            $role->remark = trim((string) $this->request->param('remark'));
        }
        $role->save();

        if ($this->request->param('menu_ids') !== null && (int) $role->is_super !== 1) {
            $this->saveMenus((int) $role->id, (array) $this->request->param('menu_ids', []));
        }
        return $this->ok([], '已保存');
    }

    public function status()
    {
        $this->checkPerm('role:status');
        $role = $this->findRole((int) $this->request->param('id', 0));
        if ((int) $role->is_super === 1) {
            throw new BizException('超管角色不允许停用');
        }
        $role->status = (int) $this->request->param('status', 1) === 1 ? 1 : 0;
        $role->save();
        return $this->ok([], $role->status ? '已启用' : '已停用');
    }

    public function delete()
    {
        $this->checkPerm('role:delete');
        $role = $this->findRole((int) $this->request->param('id', 0));
        if ((int) $role->is_super === 1) {
            throw new BizException('超管角色不允许删除');
        }
        $inUse = Admin::where('role_id', $role->id)->where('is_deleted', 0)->count();
        if ($inUse > 0) {
            throw new BizException('该角色下还有 ' . $inUse . ' 名员工，请先调整其角色');
        }
        $role->is_deleted = 1;
        $role->save();
        Db::name('role_menu')->where('role_id', $role->id)->delete();
        return $this->ok([], '已删除');
    }

    private function findRole(int $id): Role
    {
        $role = Role::where('id', $id)->where('is_deleted', 0)->find();
        if (empty($role)) {
            throw new BizException('角色不存在');
        }
        return $role;
    }

    /** 重建角色-菜单绑定（勾选父级时前端需一并带上子级 id） */
    private function saveMenus(int $roleId, array $menuIds): void
    {
        $menuIds = array_values(array_unique(array_filter(array_map('intval', $menuIds))));
        if ($menuIds) {
            $valid = Menu::whereIn('id', $menuIds)->where('is_deleted', 0)->column('id');
            $menuIds = array_map('intval', $valid);
        }
        Db::name('role_menu')->where('role_id', $roleId)->delete();
        if ($menuIds) {
            $rows = array_map(function ($mid) use ($roleId) {
                return ['role_id' => $roleId, 'menu_id' => $mid];
            }, $menuIds);
            Db::name('role_menu')->insertAll($rows);
        }
    }
}
