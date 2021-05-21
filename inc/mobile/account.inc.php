<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {
    //主公众号ＩＤ
    $tid = request::str('tid');

    //多个公众号情况下的的子公众号ＩＤ
    $xid = request::str('xid');

    //检查公众号信息
    if (empty($tid)) {
        Util::resultAlert('没有指定公众号！', 'error');
    }

    $account = Account::findOne(['uid' => $tid]);
    if (empty($account) || $account->isBanned()) {
        Util::resultAlert('公众号没有开通免费领取！', 'error');
    }

    header('location:' . Util::murl('entry', ['from' => 'account', 'account' => $tid, 'xid' => $xid]));

} elseif ($op == 'play') {
    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail(['text' => '领取失败', 'msg' => '找不到用户或者用户无法领取']);
    }

    $uid = request::trim('uid');
    $account = Account::findOne(['uid' => $uid]);
    if (empty($account)) {
        JSON::fail(['msg' => '找不到这个广告！']);
    }

    if (!$account->isVideo()) {
        JSON::fail(['msg' => '广告类型不正确！']);
    }

    $seconds = request::int('seconds');
    $duration = $account->getDuration();

    if ($seconds < $duration) {
        JSON::success(['msg' => '请继续观看']);
    }

    $device = Device::get(request::trim('device'), true);
    if (empty($device)) {
        JSON::fail(['msg' => '找不到指定设备！']);
    }

    $ticket_data = [
        'id' => Util::random(16),
        'time' => time(),
        'deviceId' => $device->getId(),
        'shadowId' => $device->getShadowId(),
        'accountId' => $account->getId(),
    ];
    
    //准备领取商品的ticket
    $user->updateSettings('last.ticket', $ticket_data);
    
    JSON::success(['redirect' => Util::murl('account', ['op' => 'get'])]);

} elseif ($op == 'get') {
    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        Util::resultAlert('找不到用户或者用户无法领取', 'error');
    }

    $ticket_data = $user->settings('last.ticket', []);
    $account = Account::get($ticket_data['accountId']);
    if (empty($account)) {
        Util::resultAlert('找不到指定的视频广告！', 'error');
    }

    $device = Device::get($ticket_data['deviceId']);
    if (empty($device)) {
        Util::resultAlert('找不到指定的设备！', 'error');
    }
    
    $tpl_data = Util::getTplData(
        [
            $user,
            $account,
            $device,
            [
                'timeout' => App::deviceWaitTimeout(),
                'user.ticket' => $ticket_data['id'],
            ],
        ]
    );

    //领取页面
    app()->getPage($tpl_data);
}
