<?php
declare(strict_types=1);

namespace app\customer\controller;

use app\common\model\InboundAttachment;
use app\common\model\InboundBox;
use app\common\model\InboundItem;
use app\common\model\InboundOrder;
use app\common\model\BoxType;
use app\common\model\Sku;
use app\common\model\Warehouse;
use app\common\exception\BizException;
use think\facade\Db;

/**
 * 入库单（一期：正向入库，货主端 草稿→待审核）
 * 按箱装箱：单(业务线/运输方式/到仓形态/柜型/柜号/封号/运单号) → 箱(箱型/箱号/尺寸/毛重/托盘号) → 箱内SKU明细
 * hw_inbound_item 为整单 SKU 汇总（各箱自动聚合），单号 IN{Ymd}-{seq}（hw_sequence 原子自增）
 */
class Inbound extends Base
{
    /** 列表排序白名单 */
    private const SORTABLE = ['id', 'order_no', 'create_time', 'expect_time'];

    /**
     * 可选仓库列表（启用，货主只读）
     * GET /customer/inbound/warehouses
     */
    public function warehouses()
    {
        $rows = Warehouse::where('status', 1)->where('is_deleted', 0)
            ->field('id,code,name,country,city')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return $this->ok($rows);
    }

    /**
     * 箱型档案（公共箱型 + 本货主私有箱型，启用）
     * GET /customer/inbound/boxTypes
     */
    public function boxTypes()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $rows = BoxType::where('status', BoxType::ON)
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', 0)->whereOr('customer_id', $customerId);
            })
            ->field('id,customer_id,code,name,length,width,height,tare_weight,max_weight')
            ->order('customer_id', 'asc')
            ->order('code', 'asc')
            ->select()
            ->toArray();
        foreach ($rows as &$r) {
            $r['is_public'] = ((int) $r['customer_id'] === 0);
            $r['volume']    = round(
                (float) $r['length'] * (float) $r['width'] * (float) $r['height'] / 1000000,
                4
            );
        }
        unset($r);
        return $this->ok($rows);
    }

    /**
     * 入库单列表（分页 + 关键词 + 状态）
     * GET /customer/inbound/list?page=1&limit=20&keyword=IN...&state=10
     */
    public function list()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $keyword = trim((string) $this->request->param('keyword', ''));
        $state = $this->request->param('state', '');

        $query = InboundOrder::where('customer_id', $customerId)->where('is_deleted', 0);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('order_no', '%' . $keyword . '%')
                  ->whereOr('ref_no', 'like', '%' . $keyword . '%')
                  ->whereOr('container_no', 'like', '%' . $keyword . '%')
                  ->whereOr('tracking_no', 'like', '%' . $keyword . '%');
            });
        }
        if ($state !== '' && $state !== null) {
            $query->where('state', (int) $state);
        }

        // 目标仓库
        $warehouseId = (int) $this->request->param('warehouse_id', 0);
        if ($warehouseId > 0) {
            $query->where('warehouse_id', $warehouseId);
        }

        // 业务线：1 中转 / 2 代发
        $bizType = (int) $this->request->param('biz_type', 0);
        if (in_array($bizType, [1, 2], true)) {
            $query->where('biz_type', $bizType);
        }

        // 到仓形态：1 集装箱 / 2 托盘 / 3 散箱（历史单据数值一致，直接等值过滤）
        $handlingType = (int) $this->request->param('handling_type', 0);
        if (in_array($handlingType, [1, 2, 3], true)) {
            $query->where('handling_type', $handlingType);
        }

        // 时间范围：date_field=create 按创建时间（int 时间戳）/ expect 按预计到仓日（DATE）
        $dateField = (string) $this->request->param('date_field', 'create');
        $dateStart = $this->normalizeDate((string) $this->request->param('date_start', ''));
        $dateEnd   = $this->normalizeDate((string) $this->request->param('date_end', ''));
        if ($dateStart !== '' && $dateEnd !== '' && $dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }
        if ($dateField === 'expect') {
            if ($dateStart !== '') {
                $query->where('expect_time', '>=', $dateStart);
            }
            if ($dateEnd !== '') {
                $query->where('expect_time', '<=', $dateEnd);
            }
        } else {
            if ($dateStart !== '') {
                $query->where('create_time', '>=', strtotime($dateStart . ' 00:00:00'));
            }
            if ($dateEnd !== '') {
                $query->where('create_time', '<=', strtotime($dateEnd . ' 23:59:59'));
            }
        }

        $sort = in_array($this->request->param('sort', 'id'), self::SORTABLE, true)
            ? (string) $this->request->param('sort', 'id') : 'id';
        $order = strtolower((string) $this->request->param('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $warehouseMap = Warehouse::where('is_deleted', 0)
            ->column('name,code', 'id');

        $total = (clone $query)->count();
        $rows = $query->order($sort, $order)
            ->page($page, $limit)
            ->select()
            ->each(function ($row) use ($warehouseMap) {
                $this->formatRow($row);
                $w = $warehouseMap[$row['warehouse_id']] ?? null;
                $row['warehouse_name'] = $w['name'] ?? ('#' . $row['warehouse_id']);
                $row['warehouse_code'] = $w['code'] ?? '';
                // 摘要：箱数 / SKU 种类 / 总件数
                $row['box_count'] = InboundBox::where('inbound_id', $row['id'])->count();
                $sum = Db::name('inbound_item')
                    ->where('inbound_id', $row['id'])
                    ->field('COUNT(*) AS kinds, SUM(expect_qty) AS qty')
                    ->find();
                $row['sku_kinds']  = (int) ($sum['kinds'] ?? 0);
                $row['total_qty']  = (int) ($sum['qty'] ?? 0);
                $row['attachment_count'] = InboundAttachment::where('inbound_id', $row['id'])->count();
            })
            ->toArray();

        return $this->ok($rows, 'success', $total);
    }

    /**
     * 状态统计（左侧导航徽标）
     * GET /customer/inbound/stats
     */
    public function stats()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $rows = InboundOrder::where('customer_id', $customerId)->where('is_deleted', 0)
            ->field('state, COUNT(*) AS n')
            ->group('state')
            ->select()
            ->toArray();
        $map = array_column($rows, 'n', 'state');
        return $this->ok([
            'all'      => array_sum($map),
            'draft'    => (int) ($map[InboundOrder::ST_DRAFT] ?? 0),
            'pending'  => (int) ($map[InboundOrder::ST_PENDING] ?? 0),
            'approved' => (int) ($map[InboundOrder::ST_APPROVED] ?? 0),
            'receiving' => (int) ($map[InboundOrder::ST_RECEIVING] ?? 0),
            'received' => (int) ($map[InboundOrder::ST_RECEIVED] ?? 0),
            'shelved'  => (int) ($map[InboundOrder::ST_SHELVED] ?? 0),
            'canceled' => (int) ($map[InboundOrder::ST_CANCELED] ?? 0),
        ]);
    }

    /**
     * 入库单详情（含箱 + 箱内明细 + SKU 汇总 + 附件）
     * GET /customer/inbound/detail?id=1
     */
    public function detail()
    {
        $order = $this->findOwn((int) $this->request->param('id', 0));
        $data = $order->toArray();
        $this->formatRow($data);
        $w = Warehouse::where('id', $order->warehouse_id)->where('is_deleted', 0)->find();
        $data['warehouse_name'] = $w ? $w->name : ('#' . $order->warehouse_id);
        $data['warehouse_code'] = $w ? $w->code : '';

        // 整单 SKU 汇总（对账用）
        $data['items'] = Db::name('inbound_item')
            ->alias('i')
            ->join('sku s', 's.id = i.sku_id')
            ->where('i.inbound_id', $order->id)
            ->field('i.id, i.sku_id, i.expect_qty, i.received_qty, i.shelved_qty, i.batch_no, i.state, s.sku_code, s.barcode, s.name_cn, s.name_en, s.image, s.weight, s.length, s.width, s.height')
            ->select()
            ->toArray();

        // 箱与箱内明细：分页返回（前端滚动到底自动加载下一页）
        $boxPage  = max(1, (int) $this->request->param('box_page', 1));
        $boxLimit = min(50, max(1, (int) $this->request->param('box_limit', 8)));
        $pageData = $this->buildBoxes($order->id, $boxPage, $boxLimit);
        $data['boxes']      = $pageData['list'];
        $data['box_total']  = $pageData['total'];
        $data['box_page']   = $boxPage;
        $data['box_limit']  = $boxLimit;
        $data['box_has_more'] = $boxPage * $boxLimit < $pageData['total'];

        $data['attachments'] = InboundAttachment::where('inbound_id', $order->id)
            ->field('id,file_url,file_name,create_time')
            ->select()
            ->toArray();

        return $this->ok($data);
    }

    /**
     * 装箱明细分页（详情瀑布流加载 / 打印箱唛全量取用）
     * GET /customer/inbound/boxes?id=&page=&limit=
     */
    public function boxes()
    {
        $order = $this->findOwn((int) $this->request->param('id', 0));
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(200, max(1, (int) $this->request->param('limit', 8)));
        $data  = $this->buildBoxes($order->id, $page, $limit);
        return $this->ok([
            'list'  => $data['list'],
            'total' => $data['total'],
            'page'  => $page,
            'limit' => $limit,
            'has_more' => $page * $limit < $data['total'],
        ]);
    }

    /** 箱 + 箱内明细分页查询（公共服务，后台共用） */
    private function buildBoxes(int $inboundId, int $page, int $limit): array
    {
        return \app\common\service\InboundBoxes::build($inboundId, $page, $limit);
    }

    /**
     * 创建入库单（草稿）
     * POST /customer/inbound/create
     * {warehouse_id, biz_type, ref_no, handling_type, container_type, container_no,
     *  seal_no, pallet_qty, expect_time, remark,
     *  boxes:[{box_no?, box_type_id?, count?, length, width, height, weight, pallet_no?, items:[{sku_id, qty}]}],
     *  attachments:[{url, file_name}]}
     * count = 同规格箱数量，后端展开为 N 个箱实例（箱号自动递增）
     */
    public function create()
    {
        $customerId = (int) $this->request->auth['customer_id'];
        $data = $this->readPayload();

        Db::startTrans();
        try {
            $order = new InboundOrder();
            $order->customer_id    = $customerId;
            $order->warehouse_id   = $data['warehouse_id'];
            $order->biz_type       = $data['biz_type'];
            $order->source_type    = InboundOrder::SRC_FIRST_MILE;
            $order->ref_no         = $data['ref_no'];
            $order->transport_type = InboundOrder::TP_NONE;
            $order->handling_type  = $data['handling_type'];
            $order->container_type = $data['container_type'];
            $order->container_no   = $data['container_no'];
            $order->seal_no        = $data['seal_no'];
            $order->pallet_qty     = $data['pallet_qty'];
            $order->tracking_no    = '';
            $order->expect_time    = $data['expect_time'];
            $order->eta_date       = null;
            $order->forwarder      = '';
            $order->remark         = $data['remark'];
            $order->state          = InboundOrder::ST_DRAFT;
            $order->creator_id     = (int) $this->request->auth['admin_id'];
            $order->order_no       = $this->genOrderNo();
            $order->save();

            $this->saveBoxes($order->id, $data['boxes'], (string) $order->order_no);
            $this->saveAttachments($order->id, $data['attachments']);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return $this->ok(['id' => (int) $order->id, 'order_no' => $order->order_no], '入库单已创建');
    }

    /**
     * 编辑入库单（仅草稿；箱明细/附件全量替换）
     * POST /customer/inbound/update
     */
    public function update()
    {
        $order = $this->findOwn((int) $this->request->post('id', 0));
        if ((int) $order->state !== InboundOrder::ST_DRAFT) {
            throw new BizException('仅草稿状态的入库单可以编辑');
        }

        $data = $this->readPayload();

        Db::startTrans();
        try {
            $order->warehouse_id   = $data['warehouse_id'];
            $order->biz_type       = $data['biz_type'];
            $order->ref_no         = $data['ref_no'];
            $order->transport_type = InboundOrder::TP_NONE;
            $order->handling_type  = $data['handling_type'];
            $order->container_type = $data['container_type'];
            $order->container_no   = $data['container_no'];
            $order->seal_no        = $data['seal_no'];
            $order->pallet_qty     = $data['pallet_qty'];
            $order->tracking_no    = '';
            $order->expect_time    = $data['expect_time'];
            $order->eta_date       = null;
            $order->forwarder      = '';
            $order->remark         = $data['remark'];
            $order->save();

            // 箱与箱内明细全量替换
            $oldBoxIds = InboundBox::where('inbound_id', $order->id)->column('id');
            if (!empty($oldBoxIds)) {
                Db::name('inbound_box_item')->whereIn('box_id', $oldBoxIds)->delete();
                InboundBox::where('inbound_id', $order->id)->delete();
            }
            Db::name('inbound_item')->where('inbound_id', $order->id)->delete();
            $this->saveBoxes($order->id, $data['boxes'], (string) $order->order_no);

            InboundAttachment::where('inbound_id', $order->id)->delete();
            $this->saveAttachments($order->id, $data['attachments']);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return $this->ok(['id' => (int) $order->id], '入库单已保存');
    }

    /**
     * 提交入库单（草稿 → 待审核，等仓库审核）
     * POST /customer/inbound/submit  {id}
     */
    public function submit()
    {
        $order = $this->findOwn((int) $this->request->post('id', 0));
        if ((int) $order->state !== InboundOrder::ST_DRAFT) {
            throw new BizException('仅草稿状态的入库单可以提交');
        }
        $itemCount = InboundItem::where('inbound_id', $order->id)->count();
        if ($itemCount === 0) {
            throw new BizException('入库单没有装箱明细，请先添加箱与 SKU');
        }
        $order->state = InboundOrder::ST_PENDING;
        $order->submit_time = time();
        $order->save();
        return $this->ok(['id' => (int) $order->id, 'state' => 20], '入库单已提交，等待仓库审核');
    }

    /**
     * 取消入库单（草稿 / 待审核 → 已取消）
     * POST /customer/inbound/cancel  {id, reason?}
     */
    public function cancel()
    {
        $order = $this->findOwn((int) $this->request->post('id', 0));
        if (!in_array((int) $order->state, [InboundOrder::ST_DRAFT, InboundOrder::ST_PENDING], true)) {
            throw new BizException('当前状态不可取消');
        }
        $order->state = InboundOrder::ST_CANCELED;
        $order->cancel_time = time();
        $order->cancel_reason = trim((string) $this->request->post('reason', ''));
        $order->save();
        return $this->ok(['id' => (int) $order->id, 'state' => 90], '入库单已取消');
    }

    /**
     * 删除入库单（仅草稿，软删）
     * POST /customer/inbound/delete  {id}
     */
    public function delete()
    {
        $order = $this->findOwn((int) $this->request->post('id', 0));
        if ((int) $order->state !== InboundOrder::ST_DRAFT) {
            throw new BizException('仅草稿状态的入库单可以删除，待审核请使用取消');
        }
        $order->is_deleted = 1;
        $order->save();
        return $this->ok(['id' => (int) $order->id], '入库单已删除');
    }

    // ---------- 私有 ----------

    private function findOwn(int $id): InboundOrder
    {
        $order = InboundOrder::where('id', $id)
            ->where('customer_id', (int) $this->request->auth['customer_id'])
            ->where('is_deleted', 0)
            ->find();
        if (!$order) {
            throw new BizException('入库单不存在或无权访问');
        }
        return $order;
    }

    /** 读取并校验单头 + 箱明细 + 附件载荷，全部归一后返回 */
    private function readPayload(): array
    {
        $warehouseId   = (int) $this->request->post('warehouse_id', 0);
        $bizType       = (int) $this->request->post('biz_type', 0);
        $refNo         = trim((string) $this->request->post('ref_no', ''));
        $handlingType  = (int) $this->request->post('handling_type', 0);
        $containerType = (int) $this->request->post('container_type', 0);
        $containerNo   = strtoupper(str_replace([' ', '-'], '', trim((string) $this->request->post('container_no', ''))));
        $sealNo        = strtoupper(trim((string) $this->request->post('seal_no', '')));
        $palletQty     = (int) $this->request->post('pallet_qty', 0);
        $expectTime    = trim((string) $this->request->post('expect_time', ''));
        $remark        = trim((string) $this->request->post('remark', ''));
        $boxes         = (array) $this->request->post('boxes', []);
        $attachments   = (array) $this->request->post('attachments', []);

        if ($warehouseId <= 0 || !Warehouse::where('id', $warehouseId)->where('status', 1)->where('is_deleted', 0)->find()) {
            throw new BizException('请选择有效的目标仓库');
        }
        if (!in_array($bizType, [InboundOrder::BIZ_TRANSFER, InboundOrder::BIZ_DROPSHIP], true)) {
            throw new BizException('请选择业务线（中转 / 代发）');
        }
        if (!in_array($handlingType, [
            InboundOrder::HT_CONTAINER, InboundOrder::HT_PALLET, InboundOrder::HT_CARTON,
        ], true)) {
            throw new BizException('请选择到仓形态（集装箱 / 托盘 / 散箱）');
        }
        if ($refNo !== '' && mb_strlen($refNo) > 64) {
            throw new BizException('客户单号不能超过 64 个字符');
        }

        // 到仓形态决定哪些字段必填，同时清掉其余形态的脏数据
        if ($handlingType === InboundOrder::HT_CONTAINER) {
            if (!in_array($containerType, InboundOrder::CT_SELECTABLE, true)) {
                throw new BizException('请选择集装箱规格（20 尺 / 40 尺）');
            }
            if ($containerNo === '') {
                throw new BizException('集装箱到仓请填写集装箱号');
            }
            if (!preg_match('/^[A-Z]{4}\d{7}$/', $containerNo)) {
                throw new BizException('集装箱号格式不正确，应为 4 位字母 + 7 位数字，如 MSCU1234567');
            }
            $palletQty = 0;
        } elseif ($handlingType === InboundOrder::HT_PALLET) {
            if ($palletQty < 1) {
                throw new BizException('托盘到仓请填写托盘数量');
            }
            if ($palletQty > 999) {
                throw new BizException('托盘数量不能超过 999');
            }
            $containerType = InboundOrder::CT_NONE;
            $containerNo   = '';
            $sealNo        = '';
        } else {
            $palletQty     = 0;
            $containerType = InboundOrder::CT_NONE;
            $containerNo   = '';
            $sealNo        = '';
        }

        if (mb_strlen($containerNo) > 32 || mb_strlen($sealNo) > 32) {
            throw new BizException('集装箱号 / 封条号超长');
        }
        // 预计到仓时间：仓库排班的唯一输入，必填
        if ($expectTime === '') {
            throw new BizException('请填写预计到仓时间');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectTime)) {
            throw new BizException('预计到仓时间格式不正确');
        }
        if (mb_strlen($remark) > 500) {
            throw new BizException('备注不能超过 500 字');
        }
        if (count($boxes) === 0) {
            throw new BizException('请至少添加一个箱子');
        }

        // 收集全部 SKU 一次性校验归属
        $customerId = (int) $this->request->auth['customer_id'];
        $allSkuIds = [];
        foreach ($boxes as $b) {
            foreach ((array) ($b['items'] ?? []) as $it) {
                $allSkuIds[] = (int) ($it['sku_id'] ?? 0);
            }
        }
        $allSkuIds = array_values(array_unique(array_filter($allSkuIds)));
        $validSkus = empty($allSkuIds) ? [] : Sku::where('customer_id', $customerId)
            ->whereIn('id', $allSkuIds)
            ->where('is_deleted', 0)
            ->column('id', 'id');
        $validSkus = array_map('intval', array_keys($validSkus));

        // 箱型归属校验（公共箱型 customer_id=0 + 本货主私有箱型）
        $allBoxTypeIds = [];
        foreach ($boxes as $b) {
            $tid = (int) ($b['box_type_id'] ?? 0);
            if ($tid > 0) {
                $allBoxTypeIds[] = $tid;
            }
        }
        $allBoxTypeIds = array_values(array_unique($allBoxTypeIds));
        // id => code：既用于归属校验，也用于「未填箱号」时推导箱号前缀（BX-A01 → A）
        $boxTypeMap = empty($allBoxTypeIds) ? [] : BoxType::where('status', BoxType::ON)
            ->whereIn('id', $allBoxTypeIds)
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', 0)->whereOr('customer_id', $customerId);
            })
            ->column('code', 'id');

        // 箱与箱内明细归一校验
        // count = 同一箱号下的实体箱个数：箱号共用，靠序号 1..count 区分（A×10 → A_01…A_10）
        // 客户未填箱号时，按箱型前缀整批生成一个系统箱号（BOX001），序号同样从 1 开始
        $cleanBoxes = [];
        $seqMap  = []; // 箱号 => 下一个可用序号（跨行累加，同一箱号分两行填也不会重号）
        $autoSeq = []; // 前缀 => 系统自动箱号流水
        foreach ($boxes as $b) {
            $boxTypeId = (int) ($b['box_type_id'] ?? 0);
            if ($boxTypeId > 0 && !isset($boxTypeMap[$boxTypeId])) {
                throw new BizException('箱型无效或不属于当前货主');
            }
            $len = round((float) ($b['length'] ?? 0), 1);
            $wid = round((float) ($b['width'] ?? 0), 1);
            $hgt = round((float) ($b['height'] ?? 0), 1);
            $wgt = round((float) ($b['weight'] ?? 0), 2);
            if ($len < 0 || $len > 9999.9 || $wid < 0 || $wid > 9999.9 || $hgt < 0 || $hgt > 9999.9) {
                throw new BizException('箱尺寸不合法（0 - 9999.9 cm）');
            }
            if ($wgt < 0 || $wgt > 99999.99) {
                throw new BizException('箱毛重不合法（0 - 99999.99 kg）');
            }
            $palletNo = strtoupper(trim((string) ($b['pallet_no'] ?? '')));
            if (mb_strlen($palletNo) > 32) {
                throw new BizException('托盘号不能超过 32 个字符');
            }

            $items = (array) ($b['items'] ?? []);
            if (count($items) === 0) {
                throw new BizException('每个箱内至少要有一种 SKU');
            }
            if (count($items) > 100) {
                throw new BizException('单个箱内明细最多 100 行');
            }
            $cleanItems = [];
            $seen = [];
            foreach ($items as $it) {
                $skuId = (int) ($it['sku_id'] ?? 0);
                $qty   = (int) ($it['qty'] ?? 0);
                if ($skuId <= 0 || !in_array($skuId, $validSkus, true)) {
                    throw new BizException('装箱明细包含无权访问或无效的 SKU');
                }
                if (isset($seen[$skuId])) {
                    throw new BizException('同一箱内 SKU 重复，请合并数量');
                }
                if ($qty <= 0 || $qty > 100000) {
                    throw new BizException('箱内 SKU 数量必须为 1 - 100000');
                }
                $seen[$skuId] = true;
                $cleanItems[] = ['sku_id' => $skuId, 'qty' => $qty];
            }
            $boxCount = (int) ($b['count'] ?? 1);
            if ($boxCount < 1 || $boxCount > InboundOrder::BOX_COUNT_LIMIT) {
                throw new BizException('箱子数量必须为 1 - ' . InboundOrder::BOX_COUNT_LIMIT);
            }

            // 箱号：客户填的（A / CARTON01），留空则按箱型前缀自动编号（BOX001、BOX002…）
            $boxNo = $this->normalizeBoxNo((string) ($b['box_no'] ?? ''));
            if ($boxNo === '') {
                $prefix = $this->boxNoPrefix($boxTypeId, $boxTypeMap);
                $autoSeq[$prefix] = ($autoSeq[$prefix] ?? 0) + 1;
                $boxNo = sprintf('%s%03d', $prefix, $autoSeq[$prefix]);
                $seqMap[$boxNo] = 1;
            }
            if (!isset($seqMap[$boxNo])) {
                $seqMap[$boxNo] = 1;
            }

            for ($i = 0; $i < $boxCount; $i++) {
                $seq = $seqMap[$boxNo]++;
                $cleanBoxes[] = [
                    'box_type_id' => $boxTypeId,
                    'box_no'      => $boxNo,
                    'box_seq'     => $seq,
                    'tracking_no' => trim((string) ($b['tracking_no'] ?? '')),
                    'pallet_no'   => $palletNo,
                    'length'      => $len,
                    'width'       => $wid,
                    'height'      => $hgt,
                    'weight'      => $wgt,
                    'items'       => $cleanItems,
                ];
            }
        }

        if (count($cleanBoxes) > InboundOrder::BOX_COUNT_LIMIT) {
            throw new BizException('单次最多申报 ' . InboundOrder::BOX_COUNT_LIMIT . ' 箱，请分单提交');
        }

        // 集装箱到仓：申报总体积不超过规格理论容积的 95%（箱尺寸全空时不触发）
        if ($handlingType === InboundOrder::HT_CONTAINER && isset(InboundOrder::CT_VOLUME[$containerType])) {
            $totalCbm = 0.0;
            foreach ($cleanBoxes as $cb) {
                $totalCbm += $cb['length'] * $cb['width'] * $cb['height'] / 1000000;
            }
            $capacity = InboundOrder::CT_VOLUME[$containerType];
            if ($totalCbm > $capacity * InboundOrder::CT_FILL_LIMIT) {
                throw new BizException(sprintf(
                    '申报总体积 %.2f CBM 超出该规格装载上限（理论 %.2f CBM × 95%% = %.2f CBM），请核对箱尺寸或改用更大的集装箱',
                    $totalCbm,
                    $capacity,
                    $capacity * InboundOrder::CT_FILL_LIMIT
                ));
            }
        }

        // 附件校验（url 必须是本系统 /storage/inbound/ 上传产物）
        $cleanAtt = [];
        foreach ($attachments as $a) {
            $url = trim((string) ($a['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^/storage/inbound/#', $url) || mb_strlen($url) > 255) {
                throw new BizException('附件地址不合法，请重新上传');
            }
            if (count($cleanAtt) >= 10) {
                throw new BizException('附件最多 10 个');
            }
            $cleanAtt[] = [
                'url'       => $url,
                'file_name' => mb_substr(trim((string) ($a['file_name'] ?? '')), 0, 128),
            ];
        }

        return [
            'warehouse_id'   => $warehouseId,
            'biz_type'       => $bizType,
            'ref_no'         => $refNo,
            'handling_type'  => $handlingType,
            'container_type' => $containerType,
            'container_no'   => $containerNo,
            'seal_no'        => $sealNo,
            'pallet_qty'     => $palletQty,
            'expect_time'    => $expectTime,
            'remark'         => $remark,
            'boxes'          => $cleanBoxes,
            'attachments'    => $cleanAtt,
        ];
    }

    /**
     * 箱号归一：大写 + 去空格，且只允许 [A-Z0-9]
     * 不允许「-」与「_」——它们是箱唛的分隔符，混进箱号会让扫码解析歧义
     */
    private function normalizeBoxNo(string $raw): string
    {
        $no = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');
        if ($no === '') {
            return '';
        }
        if (!preg_match(InboundOrder::BOX_NO_PATTERN, $no)) {
            throw new BizException('箱号只能是字母或数字（最多 16 位），如 A、CARTON01；不能用「-」「_」和中文');
        }
        return $no;
    }

    /** 未填箱号时的前缀：取箱型编号末段的字母（BX-A01 → A），没有箱型用 BOX */
    private function boxNoPrefix(int $boxTypeId, array $boxTypeMap): string
    {
        if ($boxTypeId <= 0 || empty($boxTypeMap[$boxTypeId])) {
            return 'BOX';
        }
        $code = strtoupper((string) $boxTypeMap[$boxTypeId]);
        $seg  = preg_split('/[^A-Z0-9]+/', $code) ?: [$code];
        $last = (string) ($seg[count($seg) - 1] ?? $code);
        $letters = preg_replace('/[^A-Z]/', '', $last);
        return $letters !== '' ? substr($letters, 0, 8) : 'BOX';
    }

    /** 保存箱 + 箱内明细（含箱唛码），并聚合生成整单 SKU 汇总（hw_inbound_item） */
    private function saveBoxes(int $inboundId, array $boxes, string $orderNo): void
    {
        $agg = []; // sku_id => qty 汇总
        foreach ($boxes as $b) {
            $box = new InboundBox();
            $box->inbound_id  = $inboundId;
            $box->box_type_id = (int) ($b['box_type_id'] ?? 0);
            $box->box_no      = (string) $b['box_no'];
            $box->box_seq     = (int) $b['box_seq'];
            // 箱唛码：订单号_箱号_序号 —— 扫码枪扫出来的整串，split('_') 即可定位单据与箱
            $box->box_code    = InboundOrder::boxCode($orderNo, (string) $b['box_no'], (int) $b['box_seq']);
            $box->tracking_no = $b['tracking_no'];
            $box->pallet_no   = $b['pallet_no'] ?? '';
            $box->length      = $b['length'] ?? 0;
            $box->width       = $b['width'] ?? 0;
            $box->height      = $b['height'] ?? 0;
            $box->weight      = $b['weight'] ?? 0;
            $box->state       = 10;
            $box->save();

            $rows = [];
            foreach ($b['items'] as $it) {
                $rows[] = ['box_id' => $box->id, 'sku_id' => $it['sku_id'], 'qty' => $it['qty']];
                $agg[$it['sku_id']] = ($agg[$it['sku_id']] ?? 0) + $it['qty'];
            }
            (new \app\common\model\InboundBoxItem())->saveAll($rows);
        }

        // 整单 SKU 汇总（收货对账基准）
        if (!empty($agg)) {
            ksort($agg);
            $summary = [];
            foreach ($agg as $skuId => $qty) {
                $summary[] = [
                    'inbound_id'   => $inboundId,
                    'sku_id'       => $skuId,
                    'expect_qty'   => $qty,
                    'received_qty' => 0,
                    'shelved_qty'  => 0,
                    'batch_no'     => '',
                    'state'        => 10,
                ];
            }
            (new InboundItem())->saveAll($summary);
        }
    }

    private function saveAttachments(int $inboundId, array $attachments): void
    {
        if (empty($attachments)) {
            return;
        }
        $rows = [];
        foreach ($attachments as $a) {
            $rows[] = [
                'inbound_id' => $inboundId,
                'file_url'   => $a['url'],
                'file_name'  => $a['file_name'],
            ];
        }
        (new InboundAttachment())->saveAll($rows);
    }

    /** 校验并归一 Y-m-d 日期，非法返回空串（忽略该条件） */
    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : '';
    }

    /**
     * 单号 IN{Ymd}{4位序号}   例：IN202610220009
     * 不带分隔符：单号会拼进箱唛（订单号_箱号_序号），少一类符号少一处歧义；
     * 8 位日期 + 4 位流水定长，肉眼能断句，按字符串取最大值也不受影响。
     * hw_sequence 原子自增，避免并发下「查 max + 1」撞 uk_order_no
     */
    private function genOrderNo(): string
    {
        $prefix = 'IN' . date('Ymd');
        $key    = 'inbound_' . date('Ymd');
        $now    = time();

        // 当天已有单号的最大序号（同时兼容历史带「-」的旧格式 IN20261022-0009）
        // 注意：不能用 max()，TP6 的 max 默认会把结果强转数字，varchar 单号会变成 0
        $lastNew = InboundOrder::where('order_no', 'like', $prefix . '%')
            ->where('order_no', 'not like', $prefix . '-%')
            ->order('order_no', 'desc')
            ->value('order_no');
        $lastOld = InboundOrder::where('order_no', 'like', $prefix . '-%')
            ->order('order_no', 'desc')
            ->value('order_no');
        $maxSeq = max(
            $lastNew ? (int) substr((string) $lastNew, strlen($prefix)) : 0,
            $lastOld ? (int) substr((string) $lastOld, strlen($prefix) + 1) : 0
        );

        // 首次使用该日期键则初始化；序列落后于实际单号（历史数据 / 回滚）则自动校正
        Db::execute(
            'INSERT IGNORE INTO `hw_sequence` (`seq_key`,`seq_val`,`update_time`) VALUES (?, ?, ?)',
            [$key, $maxSeq, $now]
        );
        if ($maxSeq > 0) {
            Db::execute(
                'UPDATE `hw_sequence` SET `seq_val` = ?, `update_time` = ? WHERE `seq_key` = ? AND `seq_val` < ?',
                [$maxSeq, $now, $key, $maxSeq]
            );
        }

        // 原子自增（并发下各自拿到不同序号）
        Db::execute(
            'UPDATE `hw_sequence` SET `seq_val` = LAST_INSERT_ID(`seq_val` + 1), `update_time` = ? WHERE `seq_key` = ?',
            [$now, $key]
        );
        $row = Db::query('SELECT LAST_INSERT_ID() AS `n`');
        $seq = (int) ($row[0]['n'] ?? 0);
        if ($seq <= 0) {
            throw new BizException('入库单号生成失败，请重试');
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** 行归一：兼容模型对象（列表 each）与数组（detail） */
    private function formatRow($row): void
    {
        $row['state'] = (int) $row['state'];
        $row['biz_type'] = (int) $row['biz_type'];
        $row['transport_type'] = (int) $row['transport_type'];
        $row['handling_type'] = (int) ($row['handling_type'] ?? 3);
        $row['container_type'] = (int) ($row['container_type'] ?? 0);
        $row['pallet_qty'] = (int) ($row['pallet_qty'] ?? 0);
        $row['expect_time'] = $row['expect_time'] ? date('Y-m-d', strtotime((string) $row['expect_time'])) : '';
        $row['eta_date'] = !empty($row['eta_date']) ? date('Y-m-d', strtotime((string) $row['eta_date'])) : '';
    }
}
