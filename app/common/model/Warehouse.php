<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 仓库（系统级基础资料，货主只读）
 */
class Warehouse extends Model
{
    protected $name = 'warehouse';

    protected $autoWriteTimestamp = 'int';
}
