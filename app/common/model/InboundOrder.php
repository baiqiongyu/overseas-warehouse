<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 入库单（正向入库一期：草稿→已提交；收货上架为二期仓库端）
 */
class InboundOrder extends Model
{
    protected $name = 'inbound_order';

    protected $autoWriteTimestamp = 'int';

    /** 状态常量（编号留间隔，便于后续插入中间态；流转：10→20→30→40→50→60，10/20 可取消→90） */
    public const ST_DRAFT     = 10; // 草稿（货主编辑中，可取消）
    public const ST_PENDING   = 20; // 待审核（货主已提交，等仓库确认，可取消）
    public const ST_APPROVED  = 30; // 待入库（仓库已审核，等待收货）
    public const ST_RECEIVING = 40; // 入库中（逐箱清点）
    public const ST_RECEIVED  = 50; // 已入库（箱全收齐，待上架）
    public const ST_SHELVED   = 60; // 已上架（生成库存，终态）
    public const ST_CANCELED  = 90; // 已取消（终态）

    /** 业务线 */
    public const BIZ_TRANSFER = 1; // 中转入库
    public const BIZ_DROPSHIP = 2; // 代发入库

    /** 来源 */
    public const SRC_FIRST_MILE = 1; // 头程（一期固定）

    /** 运输方式（已停用，由到仓形态取代；保留常量仅为兼容历史数据展示） */
    public const TP_SEA_FCL    = 1; // 海运整柜
    public const TP_SEA_LCL    = 2; // 海运拼箱
    public const TP_AIR        = 3; // 空运
    public const TP_EXPRESS    = 4; // 快递
    public const TP_NONE       = 0; // 未指定（新单默认）

    /**
     * 到仓形态：决定仓库接货设备、拆几层、计费口径，也是月台排班的输入
     * 集装箱 → 拆柜 → 拆托 → 拆箱（3 层）
     * 托盘   → 拆托 → 拆箱（2 层）
     * 散箱   → 拆箱（1 层）
     */
    public const HT_CONTAINER = 1; // 集装箱直送仓
    public const HT_PALLET    = 2; // 托盘到仓
    public const HT_CARTON    = 3; // 散箱到仓

    /** 历史别名（012 时期命名，数值一致，保留避免调用方报错） */
    public const HT_FCL_DIRECT = self::HT_CONTAINER;

    /** 集装箱规格（只保留客户能确认的两种） */
    public const CT_NONE = 0; // 未填
    public const CT_20FT = 1; // 20 尺（20GP）
    public const CT_40FT = 2; // 40 尺（40HQ，当前主流柜型）

    /** 新建单据可选规格 */
    public const CT_SELECTABLE = [self::CT_20FT, self::CT_40FT];

    /**
     * 规格理论容积 CBM（ISO 668），用于装载率与申报体积上限校验
     * 3/4/5 为 012 时期的历史值，只参与计算不再可选
     */
    public const CT_VOLUME = [
        self::CT_20FT => 33.2,
        self::CT_40FT => 76.4,
        3             => 76.4, // 历史 40HQ
        4             => 86.1, // 历史 45HQ
    ];

    /** 装载率上限（纸箱堆叠与托盘都会吃空间，实际利用率通常 85%-90%） */
    public const CT_FILL_LIMIT = 0.95;

    /** 单个箱子展开数量的上限（前端「数量」字段） */
    public const BOX_COUNT_LIMIT = 500;

    /**
     * ================= 箱唛（箱标）编码 =================
     * 格式：{订单号}_{箱号}_{序号}   例：IN202610220009_A_01
     *
     * 为什么三段都用下划线：
     *   订单号已改为纯字母数字（IN + Ymd + 4 位流水，不带「-」），单号里不再有任何符号；
     *   箱号字符集同样收敛为 A-Z0-9。三段唯一的符号就是「_」本身 ——
     *   split('_') 恒为 3 段、从左读即可，扫码枪、后端、人眼三套口径完全一致。
     *   （历史单号仍带「-」（IN20261022-0009），解析不受影响，因为切分只认「_」。）
     *
     * 为什么只有一个箱子也保留序号：不省。箱唛只有一套解析规则才能稳定，
     * 「有没有序号」需要靠猜，且客户补箱、拆箱、分批到仓时老标签会失效。
     * 序号恒存在，唯一性由 (订单号, 箱号, 序号) 三元组天然保证。
     *
     * 序号含义：该箱号下的第几个实体箱（A 箱 10 个 → _01…_10），跨批次累加不重号。
     */
    public const BOX_CODE_SEP = '_';

    /** 箱号字符集：仅字母数字，不含分隔符，保证箱唛可逆解析 */
    public const BOX_NO_PATTERN = '/^[A-Z0-9]{1,16}$/';

    /** 生成箱唛码 */
    public static function boxCode(string $orderNo, string $boxNo, int $seq): string
    {
        return $orderNo . self::BOX_CODE_SEP . $boxNo . self::BOX_CODE_SEP . self::boxSeqText($seq);
    }

    /** 序号文本：至少 2 位补零（01…99，超 99 自然 3 位），保证排序与人眼对齐 */
    public static function boxSeqText(int $seq): string
    {
        return str_pad((string) max(1, $seq), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 反向解析箱唛 → ['order_no' =>, 'box_no' =>, 'seq' =>]
     * 规则：恰好 3 段，第 3 段纯数字；格式不符返回 null（扫码入仓时用于友好报错）
     */
    public static function parseBoxCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $seg = explode(self::BOX_CODE_SEP, $code);
        if (count($seg) !== 3 || $seg[0] === '' || $seg[1] === '') {
            return null;
        }
        if (!preg_match('/^\d{1,4}$/', $seg[2])) {
            return null;
        }
        return [
            'order_no' => $seg[0],
            'box_no'   => $seg[1],
            'seq'      => (int) $seg[2],
        ];
    }
}
