<?php
declare(strict_types=1);

namespace app\customer\validate;

use think\Validate;

/**
 * 登录参数校验
 */
class Login extends Validate
{
    protected $rule = [
        'email'    => 'require|email|max:128',
        'password' => 'require|length:1,32',
    ];

    protected $message = [
        'email.require'    => '请填写邮箱',
        'email.email'      => '邮箱格式不正确',
        'password.require' => '请填写密码',
    ];
}
