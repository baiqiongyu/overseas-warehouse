<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\exception\BizException;
use app\common\model\Admin;
use app\common\model\Role;
use think\facade\Db;

/**
 * 员工管理（仅员工账号，customer_id IS NULL）
 *
 * GET  /admin/staff/list          分页 + 关键词
 * POST /admin/staff/create        {email, password, realname, phone?, role_id}
 * POST /admin/staff/update        {id, realname?, phone?, role_id?}
 * POST /admin/staff/status        {id, status}
 * POST /admin/staff/resetpwd      {id, password}
 * POST /admin/staff/delete        {id}
 */
class Staff extends Base
{
    public function list()
    {
        $kw      = trim((string) $this->request->param('keyword', ''));
        $page    = max(1, (int) $this->request->param('page', 1));
        $limit   = min(100, max(1, (int) $this->request->param('limit', 20)));

        $query = Admin::whereNull('customer_id')->where('is_deleted', 0);
        if ($kw !== '') {
            $query->where(function ($q) use ($kw) {
                $q->whereLike('email', '%' . $kw . '%')
                  ->whereOr('realname', 'like', '%' . $kw . '%')
                  ->whereOr('phone', 'like', '%' . $kw . '%');
            });
        }

        $total = (clone $query)->count();
        $rows = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        $roleMap = \app\common\model\Role::where('is_deleted', 0)->column('name', 'id');
        foreach ($rows as &$r) {
            $r['role_name'] = $roleMap[$r['role_id'] ?? 0] ?? '';
            $r['last_login_time'] = $r['last_login_time'] ? date('Y-m-d H:i:s', (int) $r['last_login_time']) : '';
        }
        unset($r);

        return $this->ok($rows, 'success', $total);
    }

    public function create()
    {
        $this->checkPerm('staff:create');
        $email    = strtolower(trim((string) $this->request->param('email', '')));
        $password = (string) $this->request->param('password', '');
        $realname = trim((string) $this->request->param('realname', ''));
        $phone    = trim((string) $this->request->param('phone', ''));
        $roleId   = (int) $this->request->param('role_id', 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BizException('邮箱格式不正确');
        }
        if (strlen($password) < 8) {
            throw new BizException('密码至少 8 位');
        }
        if ($realname === '') {
            throw new BizException('请填写姓名');
        }
        $this->ensureRole($roleId);

        // 同邮箱曾软删过：复用该行（uk_email 唯一索引不允许新插入同邮箱）
        $revive = Admin::where('email', $email)->where('is_deleted', 1)->whereNull('customer_id')->find();

        $fields = [
            'phone'      => $phone,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'realname'   => $realname,
            'role_id'    => $roleId ?: null,
            'status'     => 1,
            'is_deleted' => 0,
            'creator_id' => (int) $this->request->auth['admin_id'],
        ];
        if ($revive) {
            $revive->save($fields);
            $staffId = (int) $revive->id;
        } else {
            $staff = Admin::create(['customer_id' => null, 'email' => $email] + $fields);
            $staffId = (int) $staff->id;
        }
        return $this->ok(['id' => $staffId], '员工已创建');
    }

    public function update()
    {
        $this->checkPerm('staff:update');
        $staff = $this->findStaff((int) $this->request->param('id', 0));

        if (($v = trim((string) $this->request->param('realname', ''))) !== '') {
            $staff->realname = $v;
        }
        if (($v = trim((string) $this->request->param('phone', ''))) !== '') {
            $staff->phone = $v;
        }
        if ($this->request->param('role_id') !== null) {
            $roleId = (int) $this->request->param('role_id');
            $this->ensureRole($roleId);
            $staff->role_id = $roleId ?: null;
        }
        $staff->save();
        return $this->ok([], '已保存');
    }

    public function status()
    {
        $this->checkPerm('staff:status');
        $staff = $this->findStaff((int) $this->request->param('id', 0));
        $state = (int) $this->request->param('status', 1) === 1 ? 1 : 0;
        if ($staff->id === (int) $this->request->auth['admin_id']) {
            throw new BizException('不能停用自己的账号');
        }
        $staff->status = $state;
        $staff->save();
        return $this->ok([], $state ? '已启用' : '已停用');
    }

    public function resetpwd()
    {
        $this->checkPerm('staff:resetpwd');
        $staff = $this->findStaff((int) $this->request->param('id', 0));
        $password = (string) $this->request->param('password', '');
        if (strlen($password) < 8) {
            throw new BizException('密码至少 8 位');
        }
        $staff->password = password_hash($password, PASSWORD_BCRYPT);
        $staff->save();
        return $this->ok([], '密码已重置');
    }

    public function delete()
    {
        $this->checkPerm('staff:delete');
        $staff = $this->findStaff((int) $this->request->param('id', 0));
        if ($staff->id === (int) $this->request->auth['admin_id']) {
            throw new BizException('不能删除自己的账号');
        }
        $staff->is_deleted = 1;
        $staff->save();
        return $this->ok([], '已删除');
    }

    private function findStaff(int $id): Admin
    {
        $staff = Admin::where('id', $id)->whereNull('customer_id')->where('is_deleted', 0)->find();
        if (empty($staff)) {
            throw new BizException('员工不存在');
        }
        return $staff;
    }

    private function ensureRole(int $roleId): void
    {
        if ($roleId === 0) {
            return; // 允许暂不分配角色
        }
        if (!Role::where('id', $roleId)->where('status', 1)->where('is_deleted', 0)->find()) {
            throw new BizException('角色不存在或已停用');
        }
    }
}
