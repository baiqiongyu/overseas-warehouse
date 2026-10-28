<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 货主客户（租户主体）
 */
class Customer extends Model
{
    protected $name = 'customer';

    // 整型时间戳（create_time / update_time）
    protected $autoWriteTimestamp = 'int';

    // 审核状态：0 待审核 1 通过
    const AUDIT_PENDING  = 0;
    const AUDIT_APPROVED = 1;
}
