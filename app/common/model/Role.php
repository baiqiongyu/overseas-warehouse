<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 员工角色（hw_role）
 */
class Role extends Model
{
    protected $name = 'role';

    protected $autoWriteTimestamp = 'int';
}
