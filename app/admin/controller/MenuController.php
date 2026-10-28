<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\exception\BizException;
use app\common\model\Menu;

/**
 * 菜单 / 模块 / 按钮权限管理
 *
 * GET  /admin/menu/tree   全量树（含按钮，配置页用）
 * POST /admin/menu/create {pid, type, title, path?, icon?, sort?}
 * POST /admin/menu/update {id, ...同上}
 * POST /admin/menu/status {id, status}
 * POST /admin/menu/delete {id}
 */
class MenuController extends Base
{
    public function tree()
    {
        $rows = Menu::where('is_deleted', 0)->order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        return $this->ok($this->build($rows, 0));
    }

    public function create()
    {
        $this->checkPerm('menu:create');
        $data = $this->readMenu();
        $menu = Menu::create($data + ['creator_id' => (int) $this->request->auth['admin_id']]);
        return $this->ok(['id' => (int) $menu->id], '菜单已创建');
    }

    public function update()
    {
        $this->checkPerm('menu:update');
        $menu = Menu::where('id', (int) $this->request->param('id', 0))->where('is_deleted', 0)->find();
        if (empty($menu)) {
            throw new BizException('菜单不存在');
        }
        $data = $this->readMenu((int) $menu->id);
        $menu->save($data);
        return $this->ok([], '已保存');
    }

    public function status()
    {
        $this->checkPerm('menu:status');
        $menu = Menu::where('id', (int) $this->request->param('id', 0))->where('is_deleted', 0)->find();
        if (empty($menu)) {
            throw new BizException('菜单不存在');
        }
        $menu->status = (int) $this->request->param('status', 1) === 1 ? 1 : 0;
        $menu->save();
        return $this->ok([], $menu->status ? '已启用' : '已停用');
    }

    public function delete()
    {
        $this->checkPerm('menu:delete');
        $menu = Menu::where('id', (int) $this->request->param('id', 0))->where('is_deleted', 0)->find();
        if (empty($menu)) {
            throw new BizException('菜单不存在');
        }
        $children = Menu::where('pid', $menu->id)->where('is_deleted', 0)->count();
        if ($children > 0) {
            throw new BizException('存在下级菜单/按钮，请先删除下级');
        }
        $menu->is_deleted = 1;
        $menu->save();
        return $this->ok([], '已删除');
    }

    /** 归一入参：type 合法、目录不需要 path、按钮 path 必填且唯一 */
    private function readMenu(int $selfId = 0): array
    {
        $type = (int) $this->request->param('type', 2);
        if (!in_array($type, [Menu::TYPE_DIR, Menu::TYPE_PAGE, Menu::TYPE_BUTTON], true)) {
            throw new BizException('类型不合法');
        }
        $pid  = max(0, (int) $this->request->param('pid', 0));
        $title = trim((string) $this->request->param('title', ''));
        if ($title === '') {
            throw new BizException('请填写名称');
        }

        // 层级约束：目录挂顶级/目录下，页面挂目录下，按钮挂页面下
        if ($pid > 0) {
            $parent = Menu::where('id', $pid)->where('is_deleted', 0)->find();
            if (empty($parent)) {
                throw new BizException('上级不存在');
            }
            $pType = (int) $parent->type;
            if (($type === Menu::TYPE_DIR && $pType !== Menu::TYPE_DIR)
                || ($type === Menu::TYPE_PAGE && $pType !== Menu::TYPE_DIR)
                || ($type === Menu::TYPE_BUTTON && $pType !== Menu::TYPE_PAGE)) {
                throw new BizException('层级不合法：模块下挂模块/页面，页面下挂按钮');
            }
        } elseif ($type !== Menu::TYPE_DIR) {
            throw new BizException('页面与按钮必须挂在上级节点下');
        }

        $path = '';
        if ($type !== Menu::TYPE_DIR) {
            $path = trim((string) $this->request->param('path', ''));
            if ($path === '') {
                throw new BizException($type === Menu::TYPE_PAGE ? '请填写路由 key' : '请填写权限标识');
            }
            if (!preg_match('/^[a-z][a-z0-9:_\/-]*$/i', $path)) {
                throw new BizException('路由/权限标识只允许字母数字与 : _ / -');
            }
            $dup = Menu::where('path', $path)->where('type', $type)->where('is_deleted', 0);
            if ($selfId > 0) {
                $dup->where('id', '<>', $selfId);
            }
            if ($dup->find()) {
                throw new BizException('标识「' . $path . '」已存在');
            }
        }

        return [
            'pid'   => $pid,
            'type'  => $type,
            'title' => $title,
            'path'  => $path,
            'icon'  => trim((string) $this->request->param('icon', '')),
            'sort'  => (int) $this->request->param('sort', 0),
        ];
    }

    private function build(array $rows, int $pid): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r['pid'] !== $pid) {
                continue;
            }
            $out[] = [
                'id'     => (int) $r['id'],
                'pid'    => (int) $r['pid'],
                'type'   => (int) $r['type'],
                'title'  => $r['title'],
                'path'   => $r['path'],
                'icon'   => $r['icon'],
                'sort'   => (int) $r['sort'],
                'status' => (int) $r['status'],
                'children' => $this->build($rows, (int) $r['id']),
            ];
        }
        return $out;
    }
}
