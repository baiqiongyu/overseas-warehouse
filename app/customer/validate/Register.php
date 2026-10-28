<?php
declare(strict_types=1);

namespace app\customer\validate;

use think\Validate;

/**
 * 货主注册参数校验
 */
class Register extends Validate
{
    protected $rule = [
        'company_name' => 'require|max:128',
        'contact'      => 'require|max:64',
        'email'        => 'require|email|max:128',
        'phone'        => 'require|regex:/^\+?[0-9][0-9\s\-]{5,19}$/',
        'country'      => 'max:2',
        'password'     => 'require|length:8,32|regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
        'repassword'   => 'require|confirm:password',
    ];

    protected $message = [
        'company_name.require' => '请填写公司名称',
        'company_name.max'     => '公司名称最长 128 字',
        'contact.require'      => '请填写联系人',
        'contact.max'          => '联系人最长 64 字',
        'email.require'        => '请填写邮箱',
        'email.email'          => '邮箱格式不正确',
        'email.max'            => '邮箱最长 128 字',
        'phone.require'        => '请填写联系电话',
        'phone.regex'          => '联系电话格式不正确',
        'country.max'          => '国家代码格式不正确',
        'password.require'     => '请填写密码',
        'password.length'      => '密码长度需在 8-32 位之间',
        'password.regex'       => '密码需同时包含字母和数字',
        'repassword.require'   => '请再次输入密码',
        'repassword.confirm'   => '两次输入的密码不一致',
    ];
}
