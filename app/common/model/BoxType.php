<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 箱型档案（带编号，可复用；customer_id=0 为公共箱型）
 * 箱实例继承箱型的尺寸与空箱自重，只需另填毛重
 */
class BoxType extends Model
{
    protected $name = 'box_type';

    protected $autoWriteTimestamp = 'int';

    /** 启用 */
    public const ON = 1;
    /** 停用 */
    public const OFF = 0;
}
