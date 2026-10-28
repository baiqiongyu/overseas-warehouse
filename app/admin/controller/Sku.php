<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\exception\BizException;
use app\common\model\Customer;
use app\common\model\Sku as SkuModel;
use app\customer\validate\Sku as SkuValidate;

/**
 * 产品管理（内部后台）
 * 与货主门户 SKU 功能一致，仅多一个「所属公司」维度：
 *   * 列表可按货主（公司）筛选，行上展示所属公司
 *   * 新增时必须选择所属公司，条码 = 货主编码-SKU编码
 *   * 编辑权限大于客户端：除 SKU 编码 / 所属公司外均可改（含客户申报字段与仓库实测）
 */
class Sku extends Base
{
    private const SORTABLE = ['id', 'sku_code', 'name_cn', 'create_time'];

    /**
     * 产品列表（全货主 + 公司筛选）
     * GET /admin/product/list?page&limit&keyword&status&customer_id&start_time&end_time
     */
    public function list()
    {
        $page      = max(1, (int) $this->request->param('page', 1));
        $limit     = min(100, max(1, (int) $this->request->param('limit', 20)));
        $keyword   = trim((string) $this->request->param('keyword', ''));
        $status    = $this->request->param('status', '');
        $custId    = (int) $this->request->param('customer_id', 0);
        $startTime = trim((string) $this->request->param('start_time', ''));
        $endTime   = trim((string) $this->request->param('end_time', ''));

        $query = SkuModel::alias('s')
            ->join('customer c', 'c.id = s.customer_id')
            ->where('s.is_deleted', 0)
            ->field('s.*, c.name AS company_name, c.code AS company_code');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('s.sku_code', '%' . $keyword . '%')
                  ->whereOr('s.barcode', 'like', '%' . $keyword . '%')
                  ->whereOr('s.name_cn', 'like', '%' . $keyword . '%')
                  ->whereOr('s.name_en', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== '' && $status !== null) {
            $query->where('s.status', (int) $status);
        }
        if ($custId > 0) {
            $query->where('s.customer_id', $custId);
        }
        if ($startTime !== '' && ($ts = strtotime($startTime . ' 00:00:00'))) {
            $query->where('s.create_time', '>=', $ts);
        }
        if ($endTime !== '' && ($ts = strtotime($endTime . ' 23:59:59'))) {
            $query->where('s.create_time', '<=', $ts);
        }

        $sort  = in_array($this->request->param('sort', 'id'), self::SORTABLE, true)
            ? (string) $this->request->param('sort', 'id') : 'id';
        $order = strtolower((string) $this->request->param('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = (clone $query)->count('s.id');
        $rows = $query->order('s.' . $sort, $order)
            ->page($page, $limit)
            ->select()
            ->each(function ($row) {
                $row['status'] = (int) $row['status'];
                $row['attrs']  = $this->decodeJson($row['attrs'], new \stdClass());
            })
            ->toArray();

        return $this->ok($rows, 'success', $total);
    }

    /**
     * 状态统计（全部 / 启用 / 停用）
     * GET /admin/product/stats
     */
    public function stats()
    {
        $base = SkuModel::where('is_deleted', 0);
        $all  = (clone $base)->count();
        $on   = (clone $base)->where('status', 1)->count();
        return $this->ok(['all' => $all, 'on' => $on, 'off' => $all - $on]);
    }

    /**
     * 所属公司下拉（启用货主）
     * GET /admin/product/customers
     */
    public function customers()
    {
        $rows = Customer::where('is_deleted', 0)
            ->where('status', 1)
            ->order('id', 'asc')
            ->field('id,code,name')
            ->select()
            ->toArray();
        return $this->ok($rows);
    }

    /**
     * 新增产品（代货主建档）
     * POST /admin/product/create  {customer_id, sku_code, ...同客户端}
     */
    public function create()
    {
        $this->checkPerm('product:create');

        $customerId = (int) $this->request->param('customer_id', 0);
        if ($customerId <= 0) {
            throw new BizException('请选择所属公司');
        }
        $customer = Customer::where('id', $customerId)
            ->where('is_deleted', 0)->where('status', 1)->find();
        if (!$customer) {
            throw new BizException('所属公司不存在或已停用');
        }

        $data = $this->request->only([
            'sku_code', 'name_cn', 'name_en', 'color', 'desc_cn', 'desc_en',
            'weight', 'length', 'width', 'height', 'declare_value', 'hs_code',
            'image', 'attrs',
        ], 'post');

        validate(SkuValidate::class)->scene('create')->check($data);

        $code = strtoupper(trim((string) $data['sku_code']));
        if (SkuModel::where('customer_id', $customerId)->where('sku_code', $code)->where('is_deleted', 0)->find()) {
            throw new BizException('该公司下 SKU 编码已存在：' . $code);
        }

        $sku = new SkuModel();
        foreach (['sku_code', 'name_cn', 'name_en', 'color', 'desc_cn', 'desc_en',
                  'weight', 'length', 'width', 'height', 'declare_value', 'hs_code'] as $field) {
            if (array_key_exists($field, $data)) {
                $sku->{$field} = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        $sku->sku_code    = $code;
        $sku->barcode     = $customer->code . '-' . $code;   // 系统条码：C000001-SKU-001
        $sku->image       = $this->normalizeImage($data['image'] ?? '');
        $sku->attrs       = $this->encodeAttrs($data['attrs'] ?? null);
        $sku->customer_id = $customerId;
        $sku->creator_id  = (int) $this->request->auth['admin_id'];
        $sku->save();

        return $this->ok(['id' => (int) $sku->id, 'barcode' => $sku->barcode], 'SKU 已创建');
    }

    /**
     * 编辑产品（后台大权限：除 SKU 编码 / 所属公司外均可改，含客户申报字段与仓库实测）
     * POST /admin/product/update
     */
    public function update()
    {
        $this->checkPerm('product:update');
        $sku = $this->findSku((int) $this->request->post('id', 0));

        $data = $this->request->only([
            'name_cn', 'name_en', 'color', 'desc_cn', 'desc_en',
            'weight', 'length', 'width', 'height', 'declare_value', 'hs_code',
            'image', 'attrs',
            'wh_weight', 'wh_length', 'wh_width', 'wh_height',
        ], 'post');

        // 与现有行合并后整体校验（复用客户端验证规则；未提供的字段沿用原值）
        $provided = array_filter($data, function ($v) {
            return $v !== null;
        });
        $merged = array_merge($sku->toArray(), $provided);
        validate(SkuValidate::class)->scene('update')->check($merged);

        foreach (['name_cn', 'name_en', 'color', 'desc_cn', 'desc_en', 'hs_code'] as $field) {
            if (array_key_exists($field, $provided)) {
                $sku->{$field} = is_string($provided[$field]) ? trim($provided[$field]) : $provided[$field];
            }
        }
        foreach (['weight', 'length', 'width', 'height', 'declare_value'] as $field) {
            if (array_key_exists($field, $provided)) {
                $sku->{$field} = $provided[$field];
            }
        }
        if (array_key_exists('image', $provided)) {
            $sku->image = $this->normalizeImage($provided['image']);
        }
        if (array_key_exists('attrs', $provided)) {
            $sku->attrs = $this->encodeAttrs($provided['attrs']);
        }

        // 仓库实测：可整组更新或清空；四项齐全记实测时间，有缺省视为未实测
        $whTouched = false;
        foreach (['wh_weight', 'wh_length', 'wh_width', 'wh_height'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $sku->{$field} = $this->normalizeWhValue($data[$field], $field);
            $whTouched = true;
        }
        if ($whTouched) {
            $complete = $sku->wh_weight !== null && $sku->wh_length !== null
                && $sku->wh_width !== null && $sku->wh_height !== null;
            $sku->wh_measure_time = $complete ? time() : 0;
        }

        $sku->save();

        return $this->ok(['id' => (int) $sku->id], 'SKU 已保存');
    }

    /**
     * 启用 / 停用
     * POST /admin/product/status
     */
    public function status()
    {
        $this->checkPerm('product:status');
        $sku   = $this->findSku((int) $this->request->post('id', 0));
        $state = (int) $this->request->post('status', 1);
        if (!in_array($state, [0, 1], true)) {
            throw new BizException('状态参数不正确');
        }
        $sku->status = $state;
        $sku->save();
        return $this->ok(['id' => (int) $sku->id, 'status' => $state], $state === 1 ? '已启用' : '已停用');
    }

    /**
     * 删除（软删）
     * POST /admin/product/delete
     */
    public function delete()
    {
        $this->checkPerm('product:delete');
        $sku = $this->findSku((int) $this->request->post('id', 0));
        $sku->is_deleted = 1;
        $sku->save();
        return $this->ok(['id' => (int) $sku->id], 'SKU 已删除');
    }

    /**
     * 批量打印 SKU 标签（一次只能打同一家公司的 SKU）
     * POST /admin/product/print-labels  {ids:[], nums, print_type, made_in_china}
     */
    public function printLabels()
    {
        $this->checkPerm('product:print');
        $ids  = array_filter(array_map('intval', (array) $this->request->post('ids', [])));
        $nums = max(1, min(100, (int) $this->request->post('nums', 1)));
        $type = (int) $this->request->post('print_type', 1) === 2 ? 2 : 1;
        $mic  = (bool) $this->request->post('made_in_china', 0);

        if (empty($ids)) {
            throw new BizException('请先选择要打印的 SKU');
        }

        $list = SkuModel::whereIn('id', $ids)
            ->where('is_deleted', 0)
            ->field('id,customer_id,sku_code,barcode')
            ->select()
            ->toArray();
        if (empty($list)) {
            throw new BizException('所选 SKU 不存在');
        }

        $custIds = array_unique(array_column($list, 'customer_id'));
        if (count($custIds) > 1) {
            throw new BizException('一次只能打印同一家公司的标签，请按公司分别勾选');
        }

        $customer = Customer::where('id', (int) $custIds[0])->field('name,code')->find();
        \Pdf\SkuLabel::output($list, $nums, $type, $mic, [
            'name' => (string) ($customer['name'] ?? ''),
            'code' => (string) ($customer['code'] ?? ''),
        ]);
    }

    // ---------- 私有 ----------

    /** 仓库实测字段：空串/null → NULL（未实测），否则必须为正数 */
    private function normalizeWhValue($value, string $field): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value <= 0) {
            throw new BizException('仓库实测数值必须大于 0（' . $field . '）');
        }
        return (float) $value;
    }

    private function findSku(int $id): SkuModel
    {
        $sku = SkuModel::where('id', $id)->where('is_deleted', 0)->find();
        if (!$sku) {
            throw new BizException('SKU 不存在或已删除');
        }
        return $sku;
    }

    /** 单图字段校验：必填；允许相对路径或 http(s) 外链 */
    private function normalizeImage($image): string
    {
        $image = trim((string) $image);
        if ($image === '') {
            throw new BizException('请上传商品图片');
        }
        if (!preg_match('#^(/|https?://)#i', $image) || mb_strlen($image) > 255) {
            throw new BizException('图片地址格式不正确');
        }
        return $image;
    }

    /** 属性对象校验并编码：仅允许白名单键，值必须布尔 */
    private function encodeAttrs($attrs): ?string
    {
        if ($attrs === null || $attrs === '' || $attrs === []) {
            return null;
        }
        if (!is_array($attrs)) {
            throw new BizException('规格属性格式不正确');
        }
        $clean = [];
        foreach ($attrs as $key => $value) {
            if (!in_array((string) $key, SkuValidate::ATTR_KEYS, true)) {
                throw new BizException('不支持的规格属性：' . $key);
            }
            $clean[$key] = (bool) $value;
        }
        return count($clean) > 0 ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
    }

    private function decodeJson($value, $default)
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : $default;
    }
}
