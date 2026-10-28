<?php
declare(strict_types=1);

namespace app\customer\controller;

use app\common\model\Customer;
use app\common\exception\BizException;

/**
 * 客户资料（货主门户）
 */
class Profile extends Base
{
    // 资料完整度参与字段：全部非空视为 100%
    private const COMPLETE_FIELDS = [
        'name', 'contact', 'phone', 'country', 'tax_no', 'website',
        'province', 'city', 'address', 'postcode',
    ];

    /**
     * 查看资料（需登录）
     * GET /customer/profile
     */
    public function read()
    {
        $customer = $this->findCustomer();
        $data = $this->pickFields($customer);
        $data['complete_rate'] = $this->completeRate($data);
        return $this->ok($data);
    }

    /**
     * 保存资料（需登录，仅允许白名单字段）
     * POST /customer/profile
     */
    public function save()
    {
        $customer = $this->findCustomer();

        $data = $this->request->only([
            'name', 'contact', 'phone', 'country', 'tax_no', 'website',
            'province', 'city', 'address', 'address2', 'postcode',
        ], 'post');

        validate(\app\customer\validate\Profile::class)
            ->scene('save')
            ->check($data);

        // 白名单字段逐一赋值，防止越权改 audit_state 等敏感字段
        foreach ($data as $field => $value) {
            $customer->{$field} = is_string($value) ? trim($value) : $value;
        }
        $customer->save();

        $result = $this->pickFields($customer);
        $result['complete_rate'] = $this->completeRate($result);
        return $this->ok($result, '资料已保存');
    }

    private function findCustomer(): Customer
    {
        $customer = Customer::where('id', (int) $this->request->auth['customer_id'])
            ->where('is_deleted', 0)
            ->find();
        if (!$customer) {
            throw new BizException('账号不存在或已被停用');
        }
        return $customer;
    }

    private function pickFields(Customer $customer): array
    {
        return [
            'customer_code' => $customer->code,
            'name'          => $customer->name,
            'contact'       => $customer->contact,
            'phone'         => $customer->phone,
            'email'         => $customer->email,
            'country'       => $customer->country,
            'tax_no'        => $customer->tax_no,
            'website'       => $customer->website,
            'province'      => $customer->province,
            'city'          => $customer->city,
            'address'       => $customer->address,
            'address2'      => $customer->address2,
            'postcode'      => $customer->postcode,
            'audit_state'   => (int) $customer->audit_state,
        ];
    }

    private function completeRate(array $data): int
    {
        $filled = 0;
        foreach (self::COMPLETE_FIELDS as $field) {
            if (isset($data[$field]) && trim((string) $data[$field]) !== '') {
                $filled++;
            }
        }
        return (int) round($filled / count(self::COMPLETE_FIELDS) * 100);
    }
}
