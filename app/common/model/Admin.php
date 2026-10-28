<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 系统账号（customer_id 非空 = 货主账号；NULL = 平台员工）
 */
class Admin extends Model
{
    protected $name = 'admin';

    protected $autoWriteTimestamp = 'int';

    // 密码明文不参与序列化输出
    protected $hidden = ['password'];
}
