<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\CommissionBalance;
use zovye\domain\User;
use zovye\model\commission_balanceModelObj;
use zovye\util\DBUtil;
use zovye\util\Helper;

$balance_obj = Helper::getAndCheckWithdraw(Request::int('id'));
if (is_error($balance_obj)) {
    JSON::fail($balance_obj);
}

$res = DBUtil::transactionDo(
    function () use ($balance_obj) {
        $user = User::get($balance_obj->getOpenid(), true);
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        $commission_balance = $user->getCommissionBalance();

        $total = abs($balance_obj->getXVal());

        //把手续费等相关费用一起退回
        $gcr = $balance_obj->getExtraData('gcr', []);
        if ($gcr && is_array($gcr)) {
            $crs = [];
            foreach ($gcr as $id) {
                /** @var commission_balanceModelObj $cr */
                $cr = CommissionBalance::findOne(['id' => $id]);
                if (empty($cr) || $cr->getExtraData('gid') != $balance_obj->getId()) {
                    return err('处理相关记录出错，请联系管理员！');
                }

                $total += abs($cr->getXVal());
                $crs[] = $cr;
            }
        }

        if ($total > 0) {
            $r = $commission_balance->change(
                $total,
                CommissionBalance::REFUND,
                [
                    'withdraw_id' => $balance_obj->getId(),
                    'admin' => _W('username'),
                ]
            );

            if (empty($r)) {
                return err('创建退款记录失败！');
            }

            if (isset($crs)) {
                foreach ($crs as $cr) {
                    if (!$cr->update(
                        [
                            'refund' => [
                                'time' => intval($r->getCreatetime()),
                                'refund_gid' => $r->getId(),
                            ],
                        ],
                        true
                    )) {
                        return err('更新相关记录出错，请联系管理员！');
                    }
                }
            }

            if ($balance_obj->update(['state' => 'cancelled', 'refund_id' => $r->getId()], true)) {
                return ['message' => '申请已退回，金额已退款到用户账户！'];
            }
        }

        return err('操作失败！');
    }
);

JSON::result($res);