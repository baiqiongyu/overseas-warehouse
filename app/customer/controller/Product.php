<?php
declare(strict_types=1);

namespace app\customer\controller;

use app\common\model\Customer;
use app\common\model\Sku;
use app\common\exception\BizException;
use app\customer\validate\Sku as SkuValidate;

/**
 * SKU 产品 v2（货主门户，customer_id 强隔离）
 * 条码由系统生成：货主编码-SKU编码，全局唯一
 */
class Product extends Base
{
    private const SORTABLE = ['id', 'sku_code', 'name_cn', 'create_time'];

    /**
     * SKU 列表（分页 + 关键词 + 状态 + 创建时间区间筛选）
     * GET /customer/product/list?page=1&limit=20&keyword=xx&status=1&start_time=2026-10-01&end_time=2026-10-22
     */
    public function list()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status  = $this->request->param('status', '');
        $startTime = trim((string) $this->request->param('start_time', ''));
        $endTime   = trim((string) $this->request->param('end_time', ''));

        $query = Sku::where('customer_id', $customerId)->where('is_deleted', 0);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('sku_code', '%' . $keyword . '%')
                  ->whereOr('barcode', 'like', '%' . $keyword . '%')
                  ->whereOr('name_cn', 'like', '%' . $keyword . '%')
                  ->whereOr('name_en', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        // 创建时间区间（create_time 存的是秒级时间戳，按天含当天 00:00:00 ~ 23:59:59）
        if ($startTime !== '') {
            $ts = strtotime($startTime . ' 00:00:00');
            if ($ts) {
                $query->where('create_time', '>=', $ts);
            }
        }
        if ($endTime !== '') {
            $ts = strtotime($endTime . ' 23:59:59');
            if ($ts) {
                $query->where('create_time', '<=', $ts);
            }
        }

        $sort = in_array($this->request->param('sort', 'id'), self::SORTABLE, true)
            ? (string) $this->request->param('sort', 'id') : 'id';
        $order = strtolower((string) $this->request->param('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = (clone $query)->count();
        $rows = $query->order($sort, $order)
            ->page($page, $limit)
            ->select()
            ->each(function ($row) {
                $row['status']      = (int) $row['status'];
                $row['attrs']       = $this->decodeJson($row['attrs'], new \stdClass());
            })
            ->toArray();

        return $this->ok($rows, 'success', $total);
    }

    /**
     * SKU 状态统计（左侧导航数量）
     * GET /customer/product/stats
     */
    public function stats()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $base = Sku::where('customer_id', $customerId)->where('is_deleted', 0);
        $all = (clone $base)->count();
        $on  = (clone $base)->where('status', 1)->count();
        return $this->ok(['all' => $all, 'on' => $on, 'off' => $all - $on]);
    }

    /**
     * SKU 详情
     * GET /customer/product/detail?id=1
     */
    public function detail()
    {
        $sku = $this->findOwn((int) $this->request->param('id', 0));
        $data = $sku->toArray();
        $data['attrs']  = $this->decodeJson($data['attrs'] ?? null, new \stdClass());
        return $this->ok($data);
    }

    /**
     * 新增 SKU：生成系统条码 = 货主编码-SKU编码
     * POST /customer/product/create
     */
    public function create()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $customer   = $this->findCustomer($customerId);

        $data = $this->request->only([
            'sku_code', 'name_cn', 'name_en', 'color', 'desc_cn', 'desc_en',
            'weight', 'length', 'width', 'height', 'declare_value', 'hs_code',
            'image', 'attrs',
        ], 'post');

        validate(SkuValidate::class)->scene('create')->check($data);

        $code = strtoupper(trim((string) $data['sku_code']));
        if (Sku::where('customer_id', $customerId)->where('sku_code', $code)->where('is_deleted', 0)->find()) {
            throw new BizException('SKU 编码已存在：' . $code);
        }

        $sku = new Sku();
        foreach (['sku_code', 'name_cn', 'name_en', 'color', 'desc_cn', 'desc_en',
                  'weight', 'length', 'width', 'height', 'declare_value', 'hs_code'] as $field) {
            if (array_key_exists($field, $data)) {
                $sku->{$field} = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        $sku->sku_code   = $code;
        $sku->barcode    = $customer->code . '-' . $code;   // 系统条码：C000001-SKU-001
        $sku->image      = $this->normalizeImage($data['image'] ?? '');
        $sku->attrs      = $this->encodeAttrs($data['attrs'] ?? null);
        $sku->customer_id = $customerId;
        $sku->creator_id  = (int) $this->request->auth['admin_id'];
        $sku->save();

        return $this->ok(['id' => (int) $sku->id, 'barcode' => $sku->barcode], 'SKU 已创建');
    }

    /**
     * 编辑 SKU（创建后仅可改：颜色 / 图片 / 中文描述 / 英文描述）
     * POST /customer/product/update
     */
    public function update()
    {
        $sku = $this->findOwn((int) $this->request->post('id', 0));

        $data = $this->request->only(['color', 'desc_cn', 'desc_en', 'image'], 'post');

        foreach (['color', 'desc_cn', 'desc_en'] as $field) {
            if (array_key_exists($field, $data)) {
                $sku->{$field} = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        if (array_key_exists('image', $data)) {
            $sku->image = $this->normalizeImage($data['image']);
        }
        $sku->save();

        return $this->ok(['id' => (int) $sku->id], 'SKU 已保存');
    }

    /**
     * 启用/停用
     * POST /customer/product/status
     */
    public function status()
    {
        $sku = $this->findOwn((int) $this->request->post('id', 0));
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
     * POST /customer/product/delete
     */
    public function delete()
    {
        $sku = $this->findOwn((int) $this->request->post('id', 0));
        $sku->is_deleted = 1;
        $sku->save();
        return $this->ok(['id' => (int) $sku->id], 'SKU 已删除');
    }

    /**
     * 批量打印 SKU 标签（服务端 TCPDF 出 PDF，浏览器内嵌打开直接打印）
     * POST /customer/product/print-labels  {ids:[], nums:1, print_type:1, made_in_china:0}
     * 输出 PDF 流（非 JSON）
     */
    public function printLabels()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $ids = array_filter(array_map('intval', (array) $this->request->post('ids', [])));
        $nums = max(1, min(100, (int) $this->request->post('nums', 1)));
        $type = (int) $this->request->post('print_type', 1) === 2 ? 2 : 1;
        $mic  = (bool) $this->request->post('made_in_china', 0);

        if (empty($ids)) {
            throw new BizException('请先选择要打印的 SKU');
        }

        $list = Sku::where('customer_id', $customerId)
            ->whereIn('id', $ids)
            ->where('is_deleted', 0)
            ->field('id,sku_code,barcode')
            ->select()
            ->toArray();
        if (empty($list)) {
            throw new BizException('所选 SKU 不存在或无权访问');
        }

        // 标签上印货主公司名称 + 货主编号（替代品名行）
        $customer = Customer::where('id', $customerId)->field('name,code')->find();

        \Pdf\SkuLabel::output($list, $nums, $type, $mic, [
            'name' => (string) ($customer['name'] ?? ''),
            'code' => (string) ($customer['code'] ?? ''),
        ]);
    }

    // ---------- 私有 ----------

    private function findCustomer(int $id): Customer
    {
        $customer = Customer::where('id', $id)->where('is_deleted', 0)->find();
        if (!$customer) {
            throw new BizException('账号不存在或已被停用');
        }
        return $customer;
    }

    private function findOwn(int $id): Sku
    {
        $sku = Sku::where('id', $id)
            ->where('customer_id', (int) $this->request->auth['customer_id'])
            ->where('is_deleted', 0)
            ->find();
        if (!$sku) {
            throw new BizException('SKU 不存在或无权访问');
        }
        return $sku;
    }

    /** 单图字段校验：必填；允许上传接口返回的相对路径或 http(s) 外链 */
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
        return count($clean) > 0
            ? json_encode($clean, JSON_UNESCAPED_UNICODE)
            : null;
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
