<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

if (!App::isUseAccountQRCode()) {
    JSON::fail('未启用这个功能！');
}

$account = Account::get(Request::int('id'));
if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

if (!$account->isAuth() || !$account->isServiceAccount()) {
    JSON::fail('只能是授权接入的服务号才能设置为屏幕二维码！');
}

$enable = $account->useAccountQRCode();
if ($account->useAccountQRCode(!$enable)) {
    CtrlServ::appNotifyAll($account->getAssignData());
    JSON::success($enable ? '已取消成功！' : '已设置成功！');
}

JSON::fail('设置失败！');