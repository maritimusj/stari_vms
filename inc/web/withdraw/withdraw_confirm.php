<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\util\DBUtil;

defined('IN_IA') or exit('Access Denied');

$balance_obj = Helper::getAndCheckWithdraw(Request::int('id'));
if (is_error($balance_obj)) {
    JSON::fail($balance_obj);
}

$result = DBUtil::transactionDo(function () use ($balance_obj) {
    if ($balance_obj->update(['state' => 'confirmed', 'admin' => _W('username')], true)) {
        return true;
    }

    return err('数据保存失败！');
});

if (is_error($result)) {
    JSON::fail($result);
}

JSON::success('操作成功！');