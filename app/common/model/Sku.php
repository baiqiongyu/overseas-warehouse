<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * SKU 商品（货主维度）
 */
class Sku extends Model
{
    protected $name = 'sku';

    protected $autoWriteTimestamp = 'int';
}
