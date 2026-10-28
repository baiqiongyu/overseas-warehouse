<?php
declare(strict_types=1);

namespace app\customer\validate;

use think\Validate;

/**
 * 客户资料编辑校验
 */
class Profile extends Validate
{
    protected $rule = [
        'name'     => 'require|max:128',
        'contact'  => 'require|max:64',
        'phone'    => 'require|max:32|regex:/^\+?[0-9][0-9\s\-]{5,19}$/',
        'country'  => 'max:2',
        'tax_no'   => 'max:64',
        'website'  => 'max:128',
        'province' => 'max:64',
        'city'     => 'max:64',
        'address'  => 'max:255',
        'address2' => 'max:255',
        'postcode' => 'max:20',
    ];

    protected $message = [
        'name.require'     => '请填写公司名称',
        'contact.require'  => '请填写联系人',
        'phone.require'    => '请填写联系电话',
        'phone.regex'      => '电话格式不正确',
        'country.max'      => '国家代码格式不正确',
    ];

    // 仅允许编辑这些字段，其余一律忽略
    protected $scene = [
        'save' => ['name', 'contact', 'phone', 'country', 'tax_no', 'website',
                   'province', 'city', 'address', 'address2', 'postcode'],
    ];
}
