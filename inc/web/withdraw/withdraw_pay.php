<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$balance_obj = Helper::getAndCheckWithdraw(Request::int('id'));
if (is_error($balance_obj)) {
    JSON::fail($balance_obj);
}

$result = DBUtil::transactionDo(function () use ($balance_obj) {
    return CommissionBalance::MCHPay($balance_obj);
});

if (is_error($result)) {
    JSON::fail($result);
}

JSON::success('转帐成功！');