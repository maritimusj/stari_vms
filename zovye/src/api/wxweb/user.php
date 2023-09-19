<?php

namespace zovye\api\wxweb;

use zovye\domain\CommissionBalance;
use zovye\model\userModelObj;
use zovye\Pay;
use zovye\Request;
use zovye\util\Helper;
use function zovye\err;

class user
{
    public static function payForRecharge(userModelObj $wx_app_user): array
    {
        if (!$wx_app_user->acquireLocker(\zovye\domain\User::BALANCE_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $price = intval(round(Request::float('price', 0, 2) * 100));
        if ($price < 1) {
            return err('充值金额不正确！');
        }

        return Helper::createRechargeOrder($wx_app_user, $price);
    }

    public static function rechargeResult(): array
    {
        $order_no = Request::str('orderNO');

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

    public static function rechargeList(userModelObj $wx_app_user): array
    {
        $query = $wx_app_user->getCommissionBalance()->log();
        $query->where([
            'src' => [
                CommissionBalance::ADJUST,
                CommissionBalance::RECHARGE,
                CommissionBalance::CHARGING_FEE,
                CommissionBalance::FUELING_FEE,
                CommissionBalance::WITHDRAW,
                CommissionBalance::TRANSFER_OUT,
                CommissionBalance::TRANSFER_RECEIVED,
            ],
        ]);

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);
        $query->page($page, $page_size);

        $query->orderBy('createtime DESC');

        $result = [];
        foreach ($query->findAll() as $log) {
            $result[] = CommissionBalance::format($log);
        }

        return $result;
    }
}