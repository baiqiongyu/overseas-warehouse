<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\model\InboundAttachment;
use app\common\model\InboundItem;
use app\common\model\InboundOrder;
use app\common\model\Warehouse;
use app\common\exception\BizException;
use app\common\service\InboundBoxes;
use think\facade\Db;

/**
 * 入库单管理（内部后台，全货主全局视角）
 *   * list/stats/detail：跨货主查询，行上带货主公司
 *   * approve：审核通过（待审核 20 → 待入库 30），是收货作业的前置
 *   * detail/boxes 为只读，箱明细分页与货主门户一致（复用 InboundBoxes 服务）
 */
class Inbound extends Base
{
    private const SORTABLE = ['id', 'order_no', 'create_time', 'expect_time'];

    /**
     * 入库单列表（全货主）
     * GET /admin/inbound/list?page&limit&keyword&state&warehouse_id&customer_id&biz_type&handling_type&date_field&date_start&date_end
     */
    public function list()
    {
        $page   = max(1, (int) $this->request->param('page', 1));
        $limit  = min(100, max(1, (int) $this->request->param('limit', 20)));
        $keyword = trim((string) $this->request->param('keyword', ''));
        $state  = $this->request->param('state', '');

        $query = InboundOrder::alias('o')
            ->join('customer c', 'c.id = o.customer_id')
            ->where('o.is_deleted', 0)
            ->field('o.*, c.name AS company_name, c.code AS company_code');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('o.order_no', '%' . $keyword . '%')
                  ->whereOr('o.ref_no', 'like', '%' . $keyword . '%')
                  ->whereOr('o.container_no', 'like', '%' . $keyword . '%')
                  ->whereOr('o.tracking_no', 'like', '%' . $keyword . '%')
                  ->whereOr('c.name', 'like', '%' . $keyword . '%')
                  ->whereOr('c.code', 'like', '%' . $keyword . '%');
            });
        }
        if ($state !== '' && $state !== null) {
            $query->where('o.state', (int) $state);
        }
        $warehouseId = (int) $this->request->param('warehouse_id', 0);
        if ($warehouseId > 0) {
            $query->where('o.warehouse_id', $warehouseId);
        }
        $customerId = (int) $this->request->param('customer_id', 0);
        if ($customerId > 0) {
            $query->where('o.customer_id', $customerId);
        }
        $bizType = (int) $this->request->param('biz_type', 0);
        if (in_array($bizType, [1, 2], true)) {
            $query->where('o.biz_type', $bizType);
        }
        $handlingType = (int) $this->request->param('handling_type', 0);
        if (in_array($handlingType, [1, 2, 3], true)) {
            $query->where('o.handling_type', $handlingType);
        }

        // 时间范围：create 按创建时间 / expect 按预计到仓日
        $dateField = (string) $this->request->param('date_field', 'create');
        $dateStart = $this->normalizeDate((string) $this->request->param('date_start', ''));
        $dateEnd   = $this->normalizeDate((string) $this->request->param('date_end', ''));
        if ($dateStart !== '' && $dateEnd !== '' && $dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }
        if ($dateField === 'expect') {
            if ($dateStart !== '') {
                $query->where('o.expect_time', '>=', $dateStart);
            }
            if ($dateEnd !== '') {
                $query->where('o.expect_time', '<=', $dateEnd);
            }
        } else {
            if ($dateStart !== '') {
                $query->where('o.create_time', '>=', strtotime($dateStart . ' 00:00:00'));
            }
            if ($dateEnd !== '') {
                $query->where('o.create_time', '<=', strtotime($dateEnd . ' 23:59:59'));
            }
        }

        $sort  = in_array($this->request->param('sort', 'id'), self::SORTABLE, true)
            ? (string) $this->request->param('sort', 'id') : 'id';
        $order = strtolower((string) $this->request->param('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $warehouseMap = Warehouse::where('is_deleted', 0)->column('name,code', 'id');

        $total = (clone $query)->count('o.id');
        $rows = $query->order('o.' . $sort, $order)
            ->page($page, $limit)
            ->select()
            ->each(function ($row) use ($warehouseMap) {
                $this->formatRow($row);
                $w = $warehouseMap[$row['warehouse_id']] ?? null;
                $row['warehouse_name'] = $w['name'] ?? ('#' . $row['warehouse_id']);
                $row['warehouse_code'] = $w['code'] ?? '';
                $row['box_count'] = Db::name('inbound_box')->where('inbound_id', $row['id'])->count();
                $sum = Db::name('inbound_item')
                    ->where('inbound_id', $row['id'])
                    ->field('COUNT(*) AS kinds, SUM(expect_qty) AS qty')
                    ->find();
                $row['sku_kinds'] = (int) ($sum['kinds'] ?? 0);
                $row['total_qty'] = (int) ($sum['qty'] ?? 0);
                $row['attachment_count'] = InboundAttachment::where('inbound_id', $row['id'])->count();
            })
            ->toArray();

        return $this->ok($rows, 'success', $total);
    }

    /**
     * 状态统计（全部 8 态徽标）
     * GET /admin/inbound/stats
     */
    public function stats()
    {
        $rows = InboundOrder::where('is_deleted', 0)
            ->field('state, COUNT(*) AS n')
            ->group('state')
            ->select()
            ->toArray();
        $map = array_column($rows, 'n', 'state');
        return $this->ok([
            'all'       => array_sum($map),
            'draft'     => (int) ($map[InboundOrder::ST_DRAFT] ?? 0),
            'pending'   => (int) ($map[InboundOrder::ST_PENDING] ?? 0),
            'approved'  => (int) ($map[InboundOrder::ST_APPROVED] ?? 0),
            'receiving' => (int) ($map[InboundOrder::ST_RECEIVING] ?? 0),
            'received'  => (int) ($map[InboundOrder::ST_RECEIVED] ?? 0),
            'shelved'   => (int) ($map[InboundOrder::ST_SHELVED] ?? 0),
            'canceled'  => (int) ($map[InboundOrder::ST_CANCELED] ?? 0),
        ]);
    }

    /**
     * 入库单详情（只读，含 SKU 汇总 + 箱明细分页 + 附件 + 货主信息）
     * GET /admin/inbound/detail?id=1&box_page=&box_limit=
     */
    public function detail()
    {
        $order = InboundOrder::where('id', (int) $this->request->param('id', 0))
            ->where('is_deleted', 0)
            ->find();
        if (!$order) {
            throw new BizException('入库单不存在');
        }
        $data = $order->toArray();
        $this->formatRow($data);
        $w = Warehouse::where('id', $order->warehouse_id)->where('is_deleted', 0)->find();
        $data['warehouse_name'] = $w ? $w->name : ('#' . $order->warehouse_id);
        $data['warehouse_code'] = $w ? $w->code : '';

        // 货主公司
        $cust = Db::name('customer')->where('id', $order->customer_id)
            ->field('id,code,name,contact,phone')->find();
        $data['customer'] = $cust ?: null;

        // 审核信息
        $data['audit_time_text'] = !empty($order->audit_time) ? date('Y-m-d H:i', (int) $order->audit_time) : '';

        // 整单 SKU 汇总
        $data['items'] = Db::name('inbound_item')
            ->alias('i')
            ->join('sku s', 's.id = i.sku_id')
            ->where('i.inbound_id', $order->id)
            ->field('i.id, i.sku_id, i.expect_qty, i.received_qty, i.shelved_qty, i.batch_no, i.state, s.sku_code, s.barcode, s.name_cn, s.name_en, s.image, s.weight, s.length, s.width, s.height')
            ->select()
            ->toArray();

        // 箱明细：分页瀑布流
        $boxPage  = max(1, (int) $this->request->param('box_page', 1));
        $boxLimit = min(50, max(1, (int) $this->request->param('box_limit', 8)));
        $pageData = InboundBoxes::build($order->id, $boxPage, $boxLimit);
        $data['boxes']        = $pageData['list'];
        $data['box_total']    = $pageData['total'];
        $data['box_page']     = $boxPage;
        $data['box_limit']    = $boxLimit;
        $data['box_has_more'] = $boxPage * $boxLimit < $pageData['total'];

        $data['attachments'] = InboundAttachment::where('inbound_id', $order->id)
            ->field('id,file_url,file_name,create_time')
            ->select()
            ->toArray();

        return $this->ok($data);
    }

    /**
     * 审核通过（待审核 20 → 待入库 30）
     * POST /admin/inbound/approve  {id}
     */
    public function approve()
    {
        $this->checkPerm('inbound:approve');

        $order = InboundOrder::where('id', (int) $this->request->post('id', 0))
            ->where('is_deleted', 0)
            ->find();
        if (!$order) {
            throw new BizException('入库单不存在');
        }
        if ((int) $order->state !== InboundOrder::ST_PENDING) {
            throw new BizException('仅待审核状态的入库单可以审核');
        }
        $order->state      = InboundOrder::ST_APPROVED;
        $order->audit_time = time();
        $order->audit_by   = (int) $this->request->auth['admin_id'];
        $order->save();

        return $this->ok(['id' => (int) $order->id, 'state' => 30], '已审核通过，入库单进入待入库');
    }

    // ---------- 私有 ----------

    /** 行归一（兼容模型对象与数组） */
    private function formatRow($row): void
    {
        $row['state'] = (int) $row['state'];
        $row['biz_type'] = (int) $row['biz_type'];
        $row['handling_type'] = (int) ($row['handling_type'] ?? 3);
        $row['container_type'] = (int) ($row['container_type'] ?? 0);
        $row['pallet_qty'] = (int) ($row['pallet_qty'] ?? 0);
        $row['expect_time'] = $row['expect_time'] ? date('Y-m-d', strtotime((string) $row['expect_time'])) : '';
    }

    /** 校验并归一 Y-m-d 日期，非法返回空串 */
    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : '';
    }
}
