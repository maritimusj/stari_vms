<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\User;
use zovye\model\userModelObj;
use zovye\util\Helper;
use zovye\util\LocationUtil;
use zovye\util\TemplateUtil;
use zovye\util\Util;

$device_id = Request::str('device');
$account_id = Request::str('account');
$from = Request::str('from');

if (Session::isAliAppContainer()) {
    $params = [
        'from' => $from,
        'device' => $device_id,
    ];
    if (Request::isset('lane')) {
        $params['lane'] = Request::int('lane');
    }

    $ali_entry_url = Util::murl('ali', $params);

    Response::redirect($ali_entry_url);
}

if (Session::isDouYinAppContainer()) {
    $douyin_entry_url = Util::murl('douyin', [
        'from' => $from,
        'device' => $device_id,
        'account' => $account_id,
    ]);

    Response::redirect($douyin_entry_url);
}

$params = [
    'create' => true,
    'update' => settings('user.wx.update.enabled', false),
];

$cb = null;
$device = null;
$account = null;

if ($device_id) {
    $device = Device::find($device_id, ['imei', 'shadow_id']);
    if (empty($device)) {
        Response::alert('请重新扫描设备上的二维码！', 'error');
    }

    if ($device->isMaintenance()) {
        Response::alert('设备维护中，请稍后再试！', 'error');
    }

    //开启了shadowId的设备，只能通过shadowId找到
    if ($device->isActiveQRCodeEnabled() && $device->getShadowId() !== $device_id) {
        Response::alert('设备二维码不正确！', 'error');
    }

    if ($device->isChargingDevice()) {
        Response::alert('请使用指定小程序扫描二维码！', 'error');
    }

    if (App::isSmsPromoEnabled()) {
        $theme = Helper::getTheme($device);
        if ($theme == 'promo') {
            //推广首页
            Response::smsPromoPage([
                'device' => $device->getImei(),
            ]);
        }
    }

    //如果设备没有分配任何吸粉号，则忽略用户授权
    if (empty($device->getAssignedAccounts())) {
        $params['update'] = false;
    }

    /**
     * @param userModelObj $user
     * @return array
     */
    $cb = function (userModelObj $user) use ($device) {
        //记录设备ID
        $user->setLastActiveDevice($device);

        $account = $user->getLastActiveAccount();
        if ($account) {
            return ['account' => $account];
        }

        return [];
    };

    $params['from'] = [
        'src' => 'device',
        'device' => [
            'name' => $device->getName(),
            'imei' => $device->getImei(),
        ],
        'ip' => CLIENT_IP,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    ];

} elseif ($account_id) {

    $account = Account::findOneFromUID($account_id);
    if (empty($account) || $account->isBanned()) {
        Response::alert('找不到这个任务或者任务已停用！', 'error');
    }

    //显示问卷填写页面
    if ($account->isQuestionnaire()) {
        $cb = function (userModelObj $user) use ($account) {
            $user->setLastActiveAccount($account);

            $device = $user->getLastActiveDevice();
            if ($device) {
                return ['device' => $device];
            }

            return [];
        };
    } else {
        //转跳回小程序
        if (Request::bool('jump')) {
            $cb = function (userModelObj $user) use ($account) {
                $user->setLastActiveAccount($account);

                $tpl_data = TemplateUtil::getTplData([
                    $user,
                    $account,
                    'misc' => [
                        'wx_app.username' => settings('agentWxapp.username', ''),
                    ],
                ]);

                Response::jumpPage($tpl_data);
            };
        } //如果公众号奖励为积分，显示获取积分页面
        elseif (App::isBalanceEnabled() && $account->getBonusType() == Account::BALANCE) {
            $cb = function (userModelObj $user) use ($account) {

                Response::getBalanceBonusPage([
                    'user' => $user,
                    'account' => $account,
                ]);

            };
        } else {
            /**
             * @param userModelObj $user
             * @return array
             */
            $cb = function (userModelObj $user) use ($account) {
                //记录公众号ID
                $user->setLastActiveAccount($account);

                $device = $user->getLastActiveDevice();
                if ($device) {
                    return ['device' => $device];
                }

                return [];
            };
        }
    }

    $params['from'] = [
        'src' => 'account',
        'account' => [
            'name' => $account->getName(),
            'title' => $account->getTitle(),
            'img' => $account->getImg(),
            'qrcode' => $account->getQrcode(),
            'url' => $account->getUrl(),
        ],
        'ip' => CLIENT_IP,
        'user-agent' => $_SERVER['HTTP_USER_AGENT'],
    ];
}

$user = Session::getCurrentUser($params);
if (empty($user)) {
    Response::alert('请用微信或者支付宝扫描二维码，谢谢！', 'error');
}

if ($user->isBanned()) {
    Response::alert('用户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled() && !$user->isIDCardVerified()) {
    Response::showTemplate(Theme::getThemeFile($device, 'verify_18'), [
        'verify18' => settings('user.verify_18', []),
        'entry_url' => Util::murl('entry', ['from' => $from, 'device' => $device_id, 'account' => $account_id]),
    ]);
}

if (is_callable($cb)) {
    $res = $cb($user);
    if ($res) {
        extract($res);
    }
}

if (empty($device)) {
    $device = $user->getLastActiveDevice();
    if (empty($device)) {
        //设备扫描页面
        $tpl_data = TemplateUtil::getTplData([$user, $account]);
        Response::scanPage($tpl_data);
    }
}

if (App::isPuaiEnabled() && !User::isSubscribed($user)) {
    Response::followPage([
        'user' => $user,
        'device' => $device,
    ]);
}

if ($from == 'device') {
    $user->setLastActiveAccount();
    $account = null;

    $tpl_data = TemplateUtil::getTplData([$device, $user]);

    if (Request::isset('lane')) {
        $tpl_data['lane_id'] = Request::int('lane');
    }

    //设备准备页面，检测设备是否在线等等
    if ($device->isReadyTimeout()) {
        Response::devicePreparePage($tpl_data);
    }

    //带货道参数的链接，直接进入商品购买页面
    if (Request::isset('lane')) {
        Response::deviceLanePage($tpl_data);
    }

} else {
    //清除上次的ticket
    $user->setLastActiveData('ticket', []);

    if ($account && $account->isQuestionnaire() && $account->getBonusType() == Account::BALANCE) {
        $user->cleanLastActiveData();
        Response::fillQuestionnairePage([
            'user' => $user,
            'account' => $account,
        ]);
    }
}

//检查用户定位
if (LocationUtil::mustValidate($user, $device)) {
    $user->cleanLastActiveData();
    $tpl_data = TemplateUtil::getTplData(
        [
            $user,
            $device,
            [
                'page.title' => '查找设备',
                'redirect' => Util::murl('entry', ['from' => 'location', 'device' => $device->getShadowId()]),
            ],
        ]
    );

    //定位页面
    Response::locationPage($tpl_data);
}

if ($account) {
    $tid = Request::str('tid');
    if ($account->isQuestionnaire() && $tid) {
        $acc = Account::findOneFromUID($tid);
        if ($acc) {
            $res = Helper::checkAvailable($user, $acc, $device);
        } else {
            $res = err('找不到这个任务！');
        }
    } else {
        $res = Helper::checkAvailable($user, $account, $device);
    }

    if (is_error($res)) {
        $user->cleanLastActiveData();
        $account = null;
    }
}

if (empty($account)) {
    //设置用户最后活动数据
    $tpl_data = TemplateUtil::getTplData([$user, $device]);
    $tpl_data['from'] = $from;

    //设备首页
    Response::devicePage($tpl_data);
    //调试使用
    //Response::douyinPage(['device' => $device, 'user' => $user]);
}

//处理多个关注二维码
$xid = Request::str('xid');
$more_accounts = Helper::getRequireAccounts($device, $user, $account, [$account_id, $xid]);
if ($more_accounts) {
    //准备页面广告
    $tpl_data = TemplateUtil::getTplData([$user, $account, $device]);
    $tpl_data['accounts'] = $more_accounts;

    //显示更多关注页面
    Response::moreAccountsPage($tpl_data);
}

if ($account->isQuestionnaire()) {
    Response::fillQuestionnairePage([
        'user' => $user,
        'account' => $account,
        'device' => $device,
        'tid' => $tid,
    ]);
}

$ticket_data = [
    'id' => REQUEST_ID,
    'time' => TIMESTAMP,
    'deviceId' => $device->getId(),
    'shadowId' => $device->getShadowId(),
    'accountId' => $account->getId(),
];

//准备领取商品的ticket
$user->setLastActiveData('ticket', $ticket_data);

$tpl_data = TemplateUtil::getTplData([
    $user,
    $account,
    $device,
    [
        'timeout' => App::getDeviceWaitTimeout(),
        'user.ticket' => $ticket_data['id'],
    ],
    'misc' => [
        'wx_app.username' => settings('agentWxapp.username', ''),
    ],
]);

//领取页面
Response::getPage($tpl_data);
