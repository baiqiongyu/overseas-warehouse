<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 菜单 / 模块 / 按钮权限（hw_menu）
 *
 * type: 1 模块目录  2 页面菜单  3 按钮权限
 */
class Menu extends Model
{
    protected $name = 'menu';

    protected $autoWriteTimestamp = 'int';

    public const TYPE_DIR    = 1;
    public const TYPE_PAGE   = 2;
    public const TYPE_BUTTON = 3;
}
