<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\BoxType;
use app\common\model\InboundBox;
use think\facade\Db;

/**
 * 入库单「箱 + 箱内明细」分页查询（货主门户与内部后台共用）
 * 返回 {list, total}；箱上补 box_type_code/box_type_name/volume，箱内含 SKU 信息
 */
class InboundBoxes
{
    public static function build(int $inboundId, int $page, int $limit): array
    {
        $total = InboundBox::where('inbound_id', $inboundId)->count();
        if ($total === 0) {
            return ['list' => [], 'total' => 0];
        }

        $boxes = InboundBox::where('inbound_id', $inboundId)
            ->order('id', 'asc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        // 补箱型编号与体积，供前端展示
        $typeIds = array_values(array_unique(array_filter(array_column($boxes, 'box_type_id'))));
        $typeMap = empty($typeIds) ? [] : BoxType::whereIn('id', $typeIds)->column('code,name', 'id');
        foreach ($boxes as &$bx) {
            $t = $typeMap[$bx['box_type_id']] ?? null;
            $bx['box_type_code'] = $t['code'] ?? '';
            $bx['box_type_name'] = $t['name'] ?? '';
            $bx['volume'] = round(
                (float) $bx['length'] * (float) $bx['width'] * (float) $bx['height'] / 1000000,
                4
            );
        }
        unset($bx);

        $boxIds = array_column($boxes, 'id');
        $boxItems = Db::name('inbound_box_item')
            ->alias('bi')
            ->join('sku s', 's.id = bi.sku_id')
            ->whereIn('bi.box_id', $boxIds)
            ->field('bi.id, bi.box_id, bi.sku_id, bi.qty, s.sku_code, s.barcode, s.name_cn, s.name_en, s.image')
            ->select()
            ->toArray();
        $grouped = [];
        foreach ($boxItems as $bi) {
            $grouped[$bi['box_id']][] = $bi;
        }
        foreach ($boxes as &$b) {
            $b['items'] = $grouped[$b['id']] ?? [];
        }
        unset($b);

        return ['list' => $boxes, 'total' => $total];
    }
}
