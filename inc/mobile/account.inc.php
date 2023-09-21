<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Account;
use zovye\domain\Balance;
use zovye\domain\Device;
use zovye\domain\Goods;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\domain\Questionnaire;
use zovye\model\balanceModelObj;
use zovye\util\DBUtil;
use zovye\util\Helper;
use zovye\util\TemplateUtil;
use zovye\util\Util;

$op = Request::op('default');

if ($op == 'default') {
    //主公众号ＩＤ
    $tid = Request::str('tid');

    //多个公众号情况下的的子公众号ＩＤ
    $xid = Request::str('xid');

    //检查公众号信息
    if (empty($tid)) {
        Response::alert('没有指定公众号！', 'error');
    }

    $account = Account::findOneFromUID($tid);
    if (empty($account) || $account->isBanned()) {
        Response::alert('公众号没有开通免费领取！', 'error');
    }

    Response::redirect(Util::murl('entry', ['from' => 'account', 'account' => $tid, 'xid' => $xid]));

} elseif ($op == 'play') {

    $user = Session::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail(['text' => '领取失败', 'msg' => '找不到用户或者用户无法领取']);
    }

    $uid = Request::trim('uid');
    $account = Account::findOneFromUID($uid);
    if (empty($account)) {
        JSON::fail(['msg' => '找不到这个广告！']);
    }

    if (!($account->isFlashEgg() || $account->isVideo())) {
        JSON::fail(['msg' => '广告类型不正确！']);
    }

    $seconds = Request::int('seconds');
    $duration = $account->getDuration();

    $device = Device::get(Request::trim('device'), true);
    if ($device) {
        $exclusive_locker = $account->settings('config.video.exclusive', false);
        if ($exclusive_locker) {
            $serial = Request::str('serial');
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
        'id' => Request::str('serial'),
        'time' => time(),
        'deviceId' => $device->getId(),
        'shadowId' => $device->getShadowId(),
        'accountId' => $account->getId(),
    ];

    if ($account->isFlashEgg()) {
        $ticket_data['goodsId'] = $account->getGoodsId();
    }

    //准备领取商品的ticket
    $user->setLastActiveData('ticket', $ticket_data);

    JSON::success(['redirect' => Util::murl('account', ['op' => 'get'])]);

} elseif ($op == 'get') {

    $user = Session::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        Response::alert('找不到用户或者用户无法领取', 'error');
    }

    $ticket_data = $user->getLastActiveData('ticket', []);
    if (empty($ticket_data)) {
        Response::alert('请重新扫描设备二维码！', 'error');
    }

    $account = Account::get($ticket_data['accountId']);
    if (empty($account)) {
        Response::alert('找不到指定的任务！', 'error');
    }

    $device = Device::get($ticket_data['deviceId']);
    if (empty($device)) {
        Response::alert('找不到指定的设备！', 'error');
    }

    $res = Helper::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);
    if (is_error($res)) {
        $user->setLastActiveData('ticket', []);
        Response::alert($res['message'], 'error');
    }

    $tpl_data = TemplateUtil::getTplData(
        [
            $user,
            $account,
            $device,
            [
                'timeout' => App::getDeviceWaitTimeout(),
                'user.ticket' => $ticket_data['id'],
            ],
        ]
    );

    //领取页面
    Response::getPage($tpl_data);

} elseif ($op == 'get_list') {

    $user = Session::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if ($user->isBanned()) {
        JSON::fail('用户暂时无法使用！');
    }

    if (!$user->isWxUser()) {
        JSON::success();
    }

    if (Request::has('deviceId')) {
        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }
    } else {
        $device = Device::getDummyDevice();
    }

    $include = [];
    if (Request::bool('balance')) {
        $include[] = Account::BALANCE;
    }

    if (Request::bool('commission')) {
        $free_goods_list = $device->getGoodsList($user, [Goods::AllowFree]);
        $ok = false;
        foreach ($free_goods_list as $goods) {
            if ($goods['num'] > 0) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            JSON::success();
        }
        $include[] = Account::COMMISSION;
    }

    if (empty($include)) {
        $include = [];
    }

    if (Request::is_array('type')) {
        $types = Request::array('type');
    } else {
        if (Request::str('type') == 'all') {
            $types = null;
        } else {
            $type = Request::int('type', Account::NORMAL);
            $types = [$type];
        }
    }

    if (Request::is_array('s_type')) {
        $s_types = Request::array('s_type');
    } else {
        if (Request::str('s_type') == 'all') {
            $s_types = null;
        } else {
            $s_types = [];
        }
    }

    $params = [
        'type' => $types,
        's_type' => $s_types,
        'include' => $include,
    ];

    if (Request::has('max')) {
        $params['max'] = Request::int('max');
    }

    $result = Account::getAvailableList($device, $user, $params);

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

    $user = Session::getCurrentUser();
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

    $device = Device::get(Request::str('device'), true);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $account = Account::findOneFromUID(Request::str('uid'));
    if (empty($account)) {
        JSON::fail('找不到这个小程序！');
    }

    $res = Helper::checkAvailable($user, $account, $device);
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

    $user = Session::getCurrentUser();
    if (empty($user)) {
        JSON::fail('无法获取用户信息！');
    }

    if (!App::isBalanceEnabled()) {
        JSON::fail('未开启这个功能！');
    }

    $account = Account::findOneFromUID(Request::str('account'));
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

    $user = Session::getCurrentUser();
    if (empty($user)) {
        JSON::fail('无法获取用户信息！');
    }

    $uid = Request::str('uid');
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

    $user = Session::getCurrentUser();
    if (empty($user)) {
        JSON::fail('无法获取用户信息！');
    }

    $device = null;
    $device_uid = Request::trim('device');
    if ($device_uid) {
        $device = Device::find($device_uid, ['imei', 'shadow_id']);
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }
    }

    $uid = Request::str('uid');
    $account = Account::findOneFromUID($uid);
    if (empty($account) || $account->isBanned()) {
        return err('任务不存在！');
    }

    if (Request::has('tid')) {
        $tid = Request::str('tid');
        $acc = Account::findOneFromUID($tid);
        if (empty($acc) || $acc->getConfig('questionnaire.uid') !== $uid) {
            JSON::fail('没有允许从这个公众号访问这个问卷！');
        }
    }

    $answer = Request::array('data');

    $v = $acc ?? $account;

    if ($v->getBonusType() == Account::BALANCE) {

        $result = DBUtil::transactionDo(function () use ($user, $device, $v, $answer) {

            $log = Balance::give($user, $v);
            if (is_error($log)) {
                return $log;
            }

            $res = Questionnaire::submitAnswer($v, $answer, $user, $device);
            if (is_error($res)) {
                return $res;
            }

            return $log;
        });

        if (is_error($result)) {
            JSON::fail($result);
        }

        $data = [
            'balance' => $user->getBalance()->total(),
            'bonus' => $result instanceof balanceModelObj ? $result->getXVal() : 0,
        ];

        JSON::success($data);

    } elseif ($v->getBonusType() == Account::COMMISSION) {

        $result = $account->checkAnswer($user, $answer);
        if (is_error($result)) {
            JSON::fail($result);
        }

        $ticket_data = [
            'id' => REQUEST_ID,
            'time' => time(),
            'deviceId' => $device->getId(),
            'shadowId' => $device->getShadowId(),
            'accountId' => $v->getId(),
            'answer' => $answer,
        ];

        if (isset($acc)) {
            $ticket_data['questionnaireAccountId'] = $account->getId();
        }

        $user->cleanLastActiveData();

        //准备领取商品的ticket
        $user->setLastActiveData('ticket', $ticket_data);

        JSON::success(['redirect' => Util::murl('account', ['op' => 'get'])]);
    }

    JSON::success(['msg' => '完成！']);

} elseif ($op == 'order') {
    if (!App::isLongPressOrderEnabled()) {
        JSON::fail('没有启用这个功能！');
    }

    $user = Session::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户或者用户无法领取！');
    }

    $uid = Request::str('uid');

    if (empty($uid)) {
        JSON::fail('请求参数不正确！');
    }

    $account = Account::findOneFromUID($uid);
    if (empty($account)) {
        JSON::fail('找不到这个公众号！');
    }

    $device = $user->getLastActiveDevice();
    if (empty($device)) {
        JSON::fail('请先扫描设备上的二维码！');
    }

    $res = Helper::checkAvailable($user, $account, $device);
    if (is_error($res)) {
        JSON::fail($res);
    }

    if (!Job::createAccountOrder([
        'account' => $account->getId(),
        'device' => $device->getId(),
        'user' => $user->getId(),
        'orderUID' => Order::makeUID($user, $device, date("YmdHis")),
        'ignoreGoodsNum' => 1,
    ], $account->getLongPressSeconds())) {
        JSON::fail('创建任务失败！');
    }

    JSON::success('正在出货中，请稍等！');
}