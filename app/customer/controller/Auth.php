<?php
declare(strict_types=1);

namespace app\customer\controller;

use app\common\exception\BizException;
use app\common\model\Admin;
use app\common\model\Customer;
use app\common\service\TokenService;
use app\customer\validate\Login as LoginValidate;
use app\customer\validate\Register as RegisterValidate;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 货主门户 - 注册 / 登录 / 登出 / 当前登录信息
 *
 * 注册会同时创建：hw_customer（货主档案） + hw_admin（主账号，email 登录）
 */
class Auth extends Base
{
    /** 登录失败锁定阈值与锁定时长（秒） */
    const MAX_FAILS   = 5;
    const LOCK_SECOND = 600;

    /**
     * 货主注册
     * POST /customer/auth/register
     * 参数：company_name, contact, email, password, repassword, phone?, country?
     */
    public function register()
    {
        $data = $this->request->only([
            'company_name', 'contact', 'email', 'password', 'repassword', 'phone', 'country',
        ], 'post');

        // 参数校验
        $validate = new RegisterValidate();
        if (!$validate->check($data)) {
            throw new BizException($validate->getError());
        }

        $email = strtolower(trim($data['email']));

        // 邮箱唯一性（数据库唯一索引兜底，这里先给出友好提示）
        if (Admin::where('email', $email)->where('is_deleted', 0)->find()) {
            throw new BizException('该邮箱已注册，请直接登录');
        }

        Db::startTrans();
        try {
            // 1. 创建货主档案
            $customer = Customer::create([
                'name'        => trim($data['company_name']),
                'contact'     => trim($data['contact']),
                'email'       => $email,
                'phone'       => trim((string) ($data['phone'] ?? '')),
                'country'     => strtoupper(trim((string) ($data['country'] ?? ''))),
                'audit_state' => Customer::AUDIT_APPROVED, // 当前注册自动通过，改人工审核只需改为 PENDING
                'creator_id'  => 0,
            ]);

            // 2. 货主编码按 ID 回填：C000001
            $customer->code = 'C' . str_pad((string) $customer->id, 6, '0', STR_PAD_LEFT);
            $customer->save();

            // 3. 创建主账号
            Admin::create([
                'customer_id' => $customer->id,
                'email'       => $email,
                'phone'       => trim((string) ($data['phone'] ?? '')),
                'password'    => password_hash($data['password'], PASSWORD_BCRYPT),
                'realname'    => trim($data['contact']),
                'is_main'     => 1,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('[register] ' . $e->getMessage());
            throw new BizException('注册失败，请稍后重试');
        }

        return $this->ok([
            'customer_id'   => $customer->id,
            'customer_code' => $customer->code,
            'customer_name' => $customer->name,
        ], '注册成功，请登录');
    }

    /**
     * 登录
     * POST /customer/auth/login
     * 参数：email, password
     */
    public function login()
    {
        $data = $this->request->only(['email', 'password'], 'post');

        $validate = new LoginValidate();
        if (!$validate->check($data)) {
            throw new BizException($validate->getError());
        }

        $email = strtolower(trim($data['email']));
        $ip    = $this->request->ip();

        // 简单防爆破：同一邮箱+IP 连续失败 5 次锁 10 分钟
        $failKey = 'login_fail:' . md5($email . '|' . $ip);
        $fails   = (int) Cache::get($failKey, 0);
        if ($fails >= self::MAX_FAILS) {
            throw new BizException('密码错误次数过多，请 10 分钟后再试');
        }

        /** @var Admin|null $admin */
        $admin = Admin::where('email', $email)->where('is_deleted', 0)->find();

        // 统一提示，避免暴露邮箱是否存在
        if (!$admin || !password_verify($data['password'], $admin->password)) {
            Cache::set($failKey, $fails + 1, self::LOCK_SECOND);
            throw new BizException('邮箱或密码不正确');
        }

        if ((int) $admin->status !== 1) {
            throw new BizException('账号已被停用，请联系客服');
        }

        $customer = Customer::where('id', $admin->customer_id)->where('is_deleted', 0)->find();
        if (!$customer || (int) $customer->status !== 1) {
            throw new BizException('账号已被停用，请联系客服');
        }
        if ((int) $customer->audit_state !== Customer::AUDIT_APPROVED) {
            throw new BizException('账号正在审核中，请等待审核通过');
        }

        // 登录成功：清空失败计数 + 记录登录信息
        Cache::delete($failKey);
        $admin->last_login_time = time();
        $admin->last_login_ip   = (string) $ip;
        $admin->save();

        $token = TokenService::create((int) $admin->id, (int) $customer->id);

        return $this->ok([
            'token'      => $token,
            'expires_in' => TokenService::EXPIRE,
            'user'       => [
                'admin_id'      => (int) $admin->id,
                'email'         => $admin->email,
                'realname'      => $admin->realname,
                'customer_id'   => (int) $customer->id,
                'customer_code' => $customer->code,
                'customer_name' => $customer->name,
                'country'       => $customer->country,
            ],
        ], '登录成功');
    }

    /**
     * 当前登录信息（需登录）
     * GET /customer/auth/info
     */
    public function info()
    {
        $auth = $this->request->auth;

        $admin    = Admin::where('id', $auth['admin_id'])->where('is_deleted', 0)->find();
        $customer = Customer::where('id', $auth['customer_id'])->where('is_deleted', 0)->find();
        if (!$admin || !$customer) {
            throw new BizException('账号不存在');
        }

        return $this->ok([
            'admin'    => [
                'admin_id'  => (int) $admin->id,
                'email'     => $admin->email,
                'phone'     => $admin->phone,
                'realname'  => $admin->realname,
                'is_main'   => (int) $admin->is_main,
                'last_login_time' => $admin->last_login_time,
            ],
            'customer' => [
                'customer_id'   => (int) $customer->id,
                'customer_code' => $customer->code,
                'name'          => $customer->name,
                'contact'       => $customer->contact,
                'email'         => $customer->email,
                'phone'         => $customer->phone,
                'country'       => $customer->country,
                'audit_state'   => (int) $customer->audit_state,
            ],
        ]);
    }

    /**
     * 退出登录（需登录）
     * POST /customer/auth/logout
     */
    public function logout()
    {
        TokenService::delete((string) $this->request->token);
        return $this->ok([], '已退出登录');
    }

    /**
     * 邮箱是否可用（注册页实时校验）
     * GET /customer/auth/check-email?email=xxx
     */
    public function checkEmail()
    {
        $email = strtolower(trim((string) $this->request->param('email', '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BizException('邮箱格式不正确');
        }

        $exists = (bool) Admin::where('email', $email)->where('is_deleted', 0)->find();
        return $this->ok(['email' => $email, 'available' => !$exists]);
    }
}
