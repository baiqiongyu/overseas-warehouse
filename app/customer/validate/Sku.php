<?php
declare(strict_types=1);

namespace app\customer\validate;

use think\Validate;

/**
 * SKU v2 新增/编辑校验
 * 注意：TP6 的 number 规则实为 ctype_digit（仅整数），小数字段必须用正则
 */
class Sku extends Validate
{
    /** 规格属性允许的键（超出即拒绝，防脏数据进 JSON） */
    public const ATTR_KEYS = ['battery', 'liquid', 'powder', 'magnetic', 'fragile'];

    protected $rule = [
        'sku_code'      => 'require|max:64|regex:/^[A-Za-z0-9_\-\.]+$/',
        'name_cn'       => 'require|max:128',
        'name_en'       => 'require|max:128',
        'color'         => 'max:64',
        'desc_cn'       => 'max:2000',
        'desc_en'       => 'max:2000',
        'weight'        => 'require|regex:/^\d+(\.\d+)?$/|gt:0',
        'length'        => 'require|regex:/^\d+(\.\d+)?$/|gt:0',
        'width'         => 'require|regex:/^\d+(\.\d+)?$/|gt:0',
        'height'        => 'require|regex:/^\d+(\.\d+)?$/|gt:0',
        'declare_value' => 'require|regex:/^\d+(\.\d+)?$/|egt:0',
        'hs_code'       => 'max:32',
    ];

    protected $message = [
        'sku_code.require' => '请填写 SKU 编码',
        'sku_code.regex'   => 'SKU 编码仅限字母、数字、- _ . 组成',
        'name_cn.require'  => '请填写中文品名',
        'name_en.require'  => '请填写英文品名',
        'weight.require'   => '请填写重量',
        'weight.gt'        => '重量必须大于 0',
        'length.require'   => '请填写长度',
        'length.gt'        => '长度必须大于 0',
        'width.require'    => '请填写宽度',
        'width.gt'         => '宽度必须大于 0',
        'height.require'   => '请填写高度',
        'height.gt'        => '高度必须大于 0',
        'declare_value.require' => '请填写申报价值',
        'declare_value.regex'   => '申报价值必须为非负数字',
    ];

    protected $scene = [
        'create' => ['sku_code', 'name_cn', 'name_en', 'color', 'desc_cn', 'desc_en',
                     'weight', 'length', 'width', 'height', 'declare_value', 'hs_code'],
        'update' => ['name_cn', 'name_en', 'color', 'desc_cn', 'desc_en',
                     'weight', 'length', 'width', 'height', 'declare_value', 'hs_code'],
    ];
}
