<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\exception\BizException;
use app\common\model\Warehouse as WarehouseModel;

/**
 * 仓库管理（内部后台基础资料）
 *   * 仓库为系统级基础资料，货主门户只读选择（入库单下拉）
 *   * 编码全局唯一（不区分大小写，统一转大写）
 *   * 删除保护：被入库单引用的仓库不可删除，只能停用
 */
class Warehouse extends Base
{
    /** 常用国家（ISO2），前端下拉用 */
    public const COUNTRIES = ['CN', 'US', 'CA', 'GB', 'DE', 'FR', 'JP', 'KR', 'AU', 'NL'];

    /**
     * 仓库列表
     * GET /admin/warehouse/list?page&limit&keyword&status
     */
    public function list()
    {
        $page    = max(1, (int) $this->request->param('page', 1));
        $limit   = min(100, max(1, (int) $this->request->param('limit', 20)));
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status  = $this->request->param('status', '');

        $query = WarehouseModel::where('is_deleted', 0);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('code', '%' . $keyword . '%')
                  ->whereOr('name', 'like', '%' . $keyword . '%')
                  ->whereOr('city', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $total = (clone $query)->count();
        $rows = $query->order('id', 'asc')
            ->page($page, $limit)
            ->select()
            ->each(function ($row) {
                $row['status'] = (int) $row['status'];
            })
            ->toArray();

        return $this->ok($rows, 'success', $total);
    }

    /**
     * 状态统计（全部 / 启用 / 停用）
     * GET /admin/warehouse/stats
     */
    public function stats()
    {
        $base = WarehouseModel::where('is_deleted', 0);
        $all  = (clone $base)->count();
        $on   = (clone $base)->where('status', 1)->count();
        return $this->ok(['all' => $all, 'on' => $on, 'off' => $all - $on]);
    }

    /**
     * 新增仓库
     * POST /admin/warehouse/create  {code, name, country, city, address}
     */
    public function create()
    {
        $this->checkPerm('warehouse:create');
        $data = $this->validateInput($this->request->only(['code', 'name', 'country', 'city', 'address'], 'post'));

        $wh = new WarehouseModel();
        $wh->code    = $data['code'];
        $wh->name    = $data['name'];
        $wh->country = $data['country'];
        $wh->city    = $data['city'];
        $wh->address = $data['address'];
        $wh->status  = 1;
        $wh->save();

        return $this->ok(['id' => (int) $wh->id], '仓库已创建');
    }

    /**
     * 编辑仓库（全部字段可改，编码改后仍需全局唯一）
     * POST /admin/warehouse/update  {id, code, name, country, city, address}
     */
    public function update()
    {
        $this->checkPerm('warehouse:update');
        $wh   = $this->findWarehouse((int) $this->request->post('id', 0));
        $data = $this->validateInput($this->request->only(['code', 'name', 'country', 'city', 'address'], 'post'));

        $wh->code    = $data['code'];
        $wh->name    = $data['name'];
        $wh->country = $data['country'];
        $wh->city    = $data['city'];
        $wh->address = $data['address'];
        $wh->save();

        return $this->ok(['id' => (int) $wh->id], '仓库已保存');
    }

    /**
     * 启用 / 停用
     * POST /admin/warehouse/status  {id, status}
     */
    public function status()
    {
        $this->checkPerm('warehouse:status');
        $wh    = $this->findWarehouse((int) $this->request->post('id', 0));
        $state = (int) $this->request->post('status', 1);
        if (!in_array($state, [0, 1], true)) {
            throw new BizException('状态参数不正确');
        }
        $wh->status = $state;
        $wh->save();
        return $this->ok(['id' => (int) $wh->id, 'status' => $state], $state === 1 ? '已启用' : '已停用');
    }

    /**
     * 删除（软删；被入库单引用的仓库不可删除）
     * POST /admin/warehouse/delete  {id}
     */
    public function delete()
    {
        $this->checkPerm('warehouse:delete');
        $wh = $this->findWarehouse((int) $this->request->post('id', 0));

        // 引用保护：入库单（未删除）引用中
        $refs = \think\facade\Db::name('inbound_order')
            ->where('warehouse_id', $wh->id)
            ->where('is_deleted', 0)
            ->count();
        if ($refs > 0) {
            throw new BizException('该仓库已有 ' . $refs . ' 张入库单引用，不可删除；如不再使用可停用');
        }

        $wh->is_deleted = 1;
        $wh->save();
        return $this->ok(['id' => (int) $wh->id], '仓库已删除');
    }

    // ---------- 私有 ----------

    private function findWarehouse(int $id): WarehouseModel
    {
        $wh = WarehouseModel::where('id', $id)->where('is_deleted', 0)->find();
        if (!$wh) {
            throw new BizException('仓库不存在或已删除');
        }
        return $wh;
    }

    /**
     * 字段校验并规范化：code 必填格式大写唯一，name 必填，country ISO2
     */
    private function validateInput(array $data): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{2,32}$/', $code)) {
            throw new BizException('仓库编码必填，仅限大写字母/数字/-/_，2~32 位');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 64) {
            throw new BizException('仓库名称必填，不超过 64 字');
        }
        $country = strtoupper(trim((string) ($data['country'] ?? '')));
        if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
            throw new BizException('国家请填两位 ISO 代码，如 CN / US');
        }
        $city    = trim((string) ($data['city'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        if (mb_strlen($city) > 64) {
            throw new BizException('城市不超过 64 字');
        }
        if (mb_strlen($address) > 255) {
            throw new BizException('地址不超过 255 字');
        }

        // 编码唯一（排除自身）
        $dupe = WarehouseModel::where('code', $code)->where('is_deleted', 0);
        if (!empty($data['id'])) {
            $dupe->where('id', '<>', (int) $data['id']);
        }
        if ($dupe->find()) {
            throw new BizException('仓库编码已存在：' . $code);
        }

        return ['code' => $code, 'name' => $name, 'country' => $country, 'city' => $city, 'address' => $address];
    }
}
