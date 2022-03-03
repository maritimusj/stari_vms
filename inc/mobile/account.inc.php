<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\balanceModelObj;

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

    $account = Account::findOneFromUID($tid);
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
    $account = Account::findOneFromUID($uid);
    if (empty($account)) {
        JSON::fail(['msg' => '找不到这个广告！']);
    }

    if (!$account->isVideo()) {
        JSON::fail(['msg' => '广告类型不正确！']);
    }

    $seconds = request::int('seconds');
    $duration = $account->getDuration();

    $device = Device::get(request::trim('device'), true);
    if ($device) {
        $exclusive_locker = $account->settings('config.video.exclusive', false);
        if ($exclusive_locker) {
            $serial = request::str('serial');
            if ($seconds == 0) {
                if (!Locker::try("account:video@{$device->getId()}", $serial, 0, 0, 2, $duration + 3, false)) {
                    JSON::fail([
                        'msg' => '请稍等，有人正在使用设备！',
                        'redirect' => Util::murl('entry', ['device' => $device->getShadowId()]),
                    ]);
                }
                JSON::success(['msg' => '请继续观看']);
            } elseif ($seconds < $duration) {
                if (!Locker::enter($serial)) {
                    JSON::fail([
                        'msg' => '请稍等，有人正在使用设备！!',
                        'redirect' => Util::murl('entry', ['device' => $device->getShadowId()]),
                    ]);
                }
                JSON::success(['msg' => '请继续观看']);
            } else {
                $locker = Locker::enter($serial);
                if ($locker) {
                    $locker->destroy();
                }
            }
        } else {
            if ($seconds < $duration) {
                JSON::success(['msg' => '请继续观看']);
            }
        }
    } else {
        if (!App::isBalanceEnabled()) {
            JSON::fail(['msg' => '找不到这个设备！']);
        }

        if ($seconds < $duration) {
            JSON::success(['msg' => '请继续观看']);
        }

        $result = Balance::give($user, $account);

        if (is_error($result)) {
            JSON::fail($result);
        }

        JSON::success([
            'balance' => $user->getBalance()->total(),
            'bonus' => $result instanceof balanceModelObj ? $result->getXVal() : 0,
        ]);
    }

    $ticket_data = [
        'id' => request::str('serial'),
        'time' => time(),
        'deviceId' => $device->getId(),
        'shadowId' => $device->getShadowId(),
        'accountId' => $account->getId(),
    ];

    //准备领取商品的ticket
    $user->setLastActiveData('ticket', $ticket_data);

    JSON::success(['redirect' => Util::murl('account', ['op' => 'get'])]);

} elseif ($op == 'get') {

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        Util::resultAlert('找不到用户或者用户无法领取', 'error');
    }

    $ticket_data = $user->getLastActiveData('ticket', []);
    if (empty($ticket_data)) {
        Util::resultAlert('请重新扫描设备二维码！', 'error');
    }

    $account = Account::get($ticket_data['accountId']);
    if (empty($account)) {
        Util::resultAlert('找不到指定的任务！', 'error');
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

} elseif ($op == 'get_list') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if ($user->isBanned()) {
        JSON::fail('用户暂时无法使用！');
    }

    if (!$user->isWxUser()) {
        JSON::success([]);
    }

    $device = Device::get(request::str('device'), true);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $types = request::array('types');
    $result = Account::getAvailableList($device, $user, [
        'type' => $types ?: null,
        'include' => [Account::COMMISSION],
    ]);

    foreach ($result as &$acc) {
        unset($acc['id']);
        unset($acc['name']);
        if ($acc['type'] !== Account::DOUYIN && $acc['type'] !== Account::QUESTIONNAIRE) {
            unset($acc['url']);
        }
        unset($acc['banned']);
        unset($acc['scname']);
        unset($acc['total']);
        unset($acc['count']);
        unset($acc['groupname']);
        unset($acc['orderno']);
    }

    JSON::success($result);

} elseif ($op == 'get_url') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if ($user->isBanned()) {
        JSON::fail('用户暂时无法使用！');
    }

    if (!$user->isWxUser()) {
        JSON::fail('请用微信中打开！');
    }

    if (!$user->acquireLocker('Account::wxapp')) {
        JSON::fail('正忙，请稍后再试！');
    }

    $device = Device::get(request::str('device'), true);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $account = Account::findOneFromUID(request::str('uid'));
    if (empty($account)) {
        JSON::fail('找不到这个小程序！');
    }

    $res = Util::checkAvailable($user, $account, $device);
    if (is_error($res)) {
        JSON::fail($res);
    }

    $ticket_data = [
        'id' => Util::random(16),
        'time' => time(),
        'deviceId' => $device->getId(),
        'shadowId' => $device->getShadowId(),
        'accountId' => $account->getId(),
    ];

    //准备领取商品的ticket
    $user->setLastActiveData('ticket', $ticket_data);

    JSON::success(['redirect' => Util::murl('account', ['op' => 'get'])]);

} elseif ($op == 'get_bonus') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('无法获取用户信息！');
    }

    if (!App::isBalanceEnabled()) {
        JSON::fail('未开启这个功能！');
    }

    $account = Account::findOneFromUID(request::str('account'));
    if (empty($account)) {
        JSON::fail('找不到这个公众号！');
    }

    $result = Balance::give($user, $account);
    if (is_error($result)) {
        JSON::fail($result);
    }

    $data = [
        'balance' => $user->getBalance()->total(),
        'bonus' => $result instanceof balanceModelObj ? $result->getXVal() : 0,
    ];

    JSON::success($data);

} elseif ($op == 'detail') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('无法获取用户信息！');
    }

    $uid = request::str('uid');
    $account = Account::findOneFromUID($uid);
    if (empty($account) || $account->isBanned()) {
        JSON::fail('任务不存在！');
    }

    if (!$account->isQuestionnaire()) {
        JSON::fail('任务类型不正确！');
    }

    $data = $account->format();
    $data['questions'] = $account->getQuestions($user);
    
    JSON::success([
        'uid' => $data['uid'],
        'clr' => $data['clr'],
        'title' => $data['title'],
        'descr' => $data['descr'],
        'img' => $data['img'],
        'qrcode' => $data['qrcode'],
        'questions' => $data['questions'],
    ]);

} elseif ($op == 'result') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('无法获取用户信息！');
    }

    $device = Device::get(request::int('device'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }
    
    $uid = request::str('uid');
    $account = Account::findOneFromUID($uid);
    if (empty($account) || $account->isBanned()) {
        return err('任务不存在！');
    }

    if (request::has('tid')) {
        $tid = request::str('tid');
        $acc = Account::findOneFromUID($tid);
        if (empty($acc) || $acc->getConfig('questionnaire.uid') !== $uid) {
            JSON::fail('没有允许从这个公众号访问这个问卷！');
        }
    }

    $answer = request::array('data');

    $result = Questionnaire::submitAnswer($account, $answer, $user, $device);
    if (is_error($result)) {
        JSON::fail($result);
    }

    $ticket_data = [
        'id' => REQUEST_ID,
        'logId' => $result->getId(),
        'time' => time(),
        'deviceId' => $device->getId(),
        'shadowId' => $device->getShadowId(),
        'accountId' => $account->getId(),
    ];
    
    if (isset($acc)) {
        $ticket_data['accountId'] = $acc->getId();
        $ticket_data['questionnaireAccountId'] = $account->getId();
    }

    $user->setLastActiveData();
    
    //准备领取商品的ticket
    $user->setLastActiveData('ticket', $ticket_data);

    JSON::success(['redirect' => Util::murl('account', ['op' => 'get'])]);
}