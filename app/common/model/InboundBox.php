<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 入库箱（货主按箱申报装箱；仓库按箱收货）
 */
class InboundBox extends Model
{
    protected $name = 'inbound_box';

    protected $autoWriteTimestamp = 'int';
}
