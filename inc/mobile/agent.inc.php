<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'reg') {

    $user = Session::getCurrentUser();
    if (empty($user)) {
        JSON::fail('只能从微信中打开，谢谢！');
    }

    if (!$user->isAgent()) {
        JSON::fail('您还不是我们的代理商！');
    }

    $device = Device::find(Request::trim('id'), ['id', 'imei']);
    if ($device && empty($device->getAgentId())) {
        if (Device::bind($device, $user->agent()) && $device->save()) {
            JSON::success('恭喜，注册成功！');
        }
    }

    JSON::fail('注册失败，请与管理员联系！');

} elseif ($op == 'login_scan') {

    Response::alert('请使用微信小程序扫描二维码登录，谢谢！', 'warning');
}
