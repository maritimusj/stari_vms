<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

$from = request::str('from');
$device_id = request::str('device');
$account_id = request::str('account');
$xid = request::str('xid');
$tid = request::str('tid');

if (Util::isAliAppContainer()) {
    $ali_entry_url = Util::murl('ali', [
        'from' => $from,
        'device' => $device_id,
    ]);
    Util::redirect($ali_entry_url);
} elseif (Util::isDouYinAppContainer()) {
    $douyin_entry_url = Util::murl('douyin', [
        'from' => $from,
        'device' => $device_id,
        'account' => $account_id,
    ]);
    Util::redirect($douyin_entry_url);
}

$params = [
    'create' => true,
    'update' => true,
];

$cb = null;
$device = null;
$account = null;

if ($device_id) {
    $device = Device::find($device_id, ['imei', 'shadow_id']);
    if (empty($device)) {
        Util::resultAlert('请重新扫描设备上的二维码！', 'error');
    }

    if ($device->isDown()) {
        Util::resultAlert('设备维护中，请稍后再试！', 'error');
    }

    //开启了shadowId的设备，只能通过shadowId找到
    if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $device_id) {
        Util::resultAlert('设备二维码不正确！', 'error');
    }

    if ($device->isChargingDevice()) {
        Util::resultAlert('请使用指定小程序扫描二维码！', 'error');
    }

    /**
     * @param userModelObj $user
     * @return array
     */
    $cb = function (userModelObj $user) use ($device) {
        if (App::isSmsPromoEnabled()) {
            $theme = Helper::getTheme($device);
            if ($theme == 'promo') {
                //推广首页
                app()->smsPromoPage([
                    'device' => $device->getImei(),
                ]);
            }
        }

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
        Util::resultAlert('找不到这个任务或者任务已停用！', 'error');
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
        if (request::bool('jump')) {
            $cb = function(userModelObj $user) use($account) {
                $user->setLastActiveAccount($account);
                
                $tpl_data = Util::getTplData([
                    $user,
                    $account,
                    'misc' => [
                        'wx_app.username' => settings('agentWxapp.username', ''),
                    ]
                ]);
                
                app()->jumpPage($tpl_data);
            };
        }
        //如果公众号奖励为积分，显示获取积分页面
        elseif (App::isBalanceEnabled() && $account->getBonusType() == Account::BALANCE) {
            $cb = function (userModelObj $user) use ($account) {
                app()->getBalanceBonusPage($user, $account);
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

$user = Util::getCurrentUser($params);
if (empty($user)) {
    Util::resultAlert('请用微信或者支付宝扫描二维码，谢谢！', 'error');
}

if ($user->isBanned()) {
    Util::resultAlert('用户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled() && !$user->isIDCardVerified()) {
    app()->showTemplate(Theme::file('verify_18'), [
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

if ($from == 'device') {
    $user->setLastActiveAccount();
    $account = null;

    if ($device->isReadyTimeout()) {
        //设备准备页面，检测设备是否在线等等
        $tpl_data = Util::getTplData([$device, $user]);
        app()->devicePreparePage($tpl_data);
    }

} else {
    //清除上次的ticket
    $user->setLastActiveData('ticket', []);

    if ($account && $account->isQuestionnaire() && $account->getBonusType() == Account::BALANCE) {
        $user->cleanLastActiveData();
        app()->fillQuestionnairePage($user, $account);
    }
}

if (empty($device)) {
    //设备扫描页面
    $tpl_data = Util::getTplData([$user, $account]);
    app()->scanPage($tpl_data);
}

//检查用户定位
if (Util::mustValidateLocation($user, $device)) {

    $user->cleanLastActiveData();
    $tpl_data = Util::getTplData(
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
    app()->locationPage($tpl_data);
}

if ($account) {
    if ($account->isQuestionnaire() && $tid) {
        $acc = Account::findOneFromUID($tid);
        if ($acc) {
            $res = Util::checkAvailable($user, $acc, $device);
        } else {
            $res = err('找不到这个任务！');
        }
    } else {
        $res = Util::checkAvailable($user, $account, $device);
    }
    if (is_error($res)) {
        $user->cleanLastActiveData();
        $account = null;
    }
}

if (empty($account)) {
    //设置用户最后活动数据
    $tpl_data = Util::getTplData([$user, $device]);
    $tpl_data['from'] = $from;

    //设备首页
    app()->devicePage($tpl_data);
    //调试使用
    //app()->douyinPage($device, $user);
}

//处理多个关注二维码
$more_accounts = Util::getRequireAccounts($device, $user, $account, [$account_id, $xid]);
if ($more_accounts) {
    //准备页面广告
    $tpl_data = Util::getTplData([$user, $account, $device]);
    $tpl_data['accounts'] = $more_accounts;

    //显示更多关注页面
    app()->moreAccountsPage($tpl_data);
}

if ($account->isQuestionnaire()) {
    app()->fillQuestionnairePage($user, $account, $device, $tid);
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

$tpl_data = Util::getTplData([
    $user,
    $account,
    $device,
    [
        'timeout' => App::deviceWaitTimeout(),
        'user.ticket' => $ticket_data['id'],
    ],
    'misc' => [
        'wx_app.username' => settings('agentWxapp.username', ''),
    ]
]);


//领取页面
app()->getPage($tpl_data);
