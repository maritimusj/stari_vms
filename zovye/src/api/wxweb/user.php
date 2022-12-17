<?php

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\CommissionBalance;
use zovye\Helper;
use zovye\Pay;
use zovye\request;
use function zovye\err;

class user
{
    public static function payForRecharge(): array
    {
        $user = common::getWXAppUser();

        if (!$user->acquireLocker(\zovye\User::BALANCE_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $price = intval(round(request::float('price', 0, 2) * 100));
        if ($price < 1) {
            return err('充值金额不正确！');
        }

        return Helper::createRechargeOrder($user, $price);
    }

    public static function rechargeResult(): array
    {
        $order_no = request::str('orderNO');

        $pay_log = Pay::getPayLog($order_no, LOG_RECHARGE);
        if (empty($pay_log)) {
            return err('找不到这个支付记录!');
        }

        if ($pay_log->isRecharged()) {
            return ['msg' => '充值已到账！', 'code' => 200];
        }

        if ($pay_log->isCancelled()) {
            return err('支付已取消');
        }

        if ($pay_log->isTimeout()) {
            return err('支付已超时！');
        }

        if ($pay_log->isRefund()) {
            return err('支付已退款！');
        }

        if ($pay_log->isPaid()) {
            return ['msg' => '支付成功！'];
        }

        return ['msg' => '正在查询..'];
    }

    public static function rechargeList(): array
    {
        $user = common::getWXAppUser();

        $query = $user->getCommissionBalance()->log();
        $query->where([
            'src' => [
                CommissionBalance::RECHARGE,
                CommissionBalance::CHARGING_FEE,
                CommissionBalance::WITHDRAW,
                CommissionBalance::TRANSFER_OUT,
                CommissionBalance::TRANSFER_RECEIVED,
            ],
        ]);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);
        $query->page($page, $page_size);

        $query->orderBy('createtime DESC');

        $result = [];
        foreach ($query->findAll() as $log) {
            $result[] = CommissionBalance::format($log);
        }

        return $result;
    }
}