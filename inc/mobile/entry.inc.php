<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

$from = request::str('from');
$device_id = request::str('device');
$account_id = request::str('account');
$xid = request::str('xid');

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
    /**
     * @param userModelObj $user
     * @return array
     */
    $cb = function (userModelObj $user) use ($device) {
        //记录设备ID
        $user->updateSettings('last.deviceId', $device->getId());
        $user->updateSettings('last.time', time());

        $last_visited_accountId = $user->settings('last.accountId');
        if ($last_visited_accountId) {
            $account = Account::get($last_visited_accountId);
            if ($account) {
                return ['account' => $account];
            }
        }

        return [];
    };

    $params['yzshop'] = [
        'agent' => $device->getAgent(),
    ];

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

    $account = Account::findOne(['uid' => $account_id]);
    if (empty($account) || $account->isBanned()) {
        Util::resultAlert('公众号没有开通免费领取！', 'error');
    }
    /**
     * @param userModelObj $user
     * @return array
     */
    $cb = function (userModelObj $user) use ($account) {
        //用户从公众号链接进入的话，检查超时
        if ($user->settings('last.deviceId') && time() - $user->settings('last.time') > settings(
                'user.scanAlive',
                VISIT_DATA_TIMEOUT
            )) {
            //设备扫描页面
            $tpl_data = Util::getTplData([$user, $account]);
            app()->scanPage($tpl_data);
        }

        //记录公众号ID
        $user->updateSettings('last.accountId', $account->getId());

        $last_deviceId = $user->settings('last.deviceId');
        if ($last_deviceId) {
            $device = Device::get($last_deviceId);
            if ($device) {
                return ['device' => $device];
            }
        }

        return [];
    };

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
    Util::resultAlert('用户帐户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled()) {
    if(!$user->isIDCardVerified()) {
        app()->showTemplate(Theme::file('verify_18'), [
            'verify18' => settings('user.verify_18', []),
            'entry_url' => Util::murl('entry', ['from' => $from, 'device' => $device_id, 'account' => $account_id]),
        ]);
    }
}

if ($from == 'device') {
    if ($device && time() - $device->settings('last.online', 0) > 60) {
        //设备准备页面，检测设备是否在线等等
        $tpl_data = Util::getTplData([$device, $user]);
        app()->devicePreparePage($tpl_data);
    }
    $user->remove('last');
} else {
    //清除上次的ticket
    $user->updateSettings('last.ticket', []);
}

if (is_callable($cb)) {
    $res = $cb($user);
    if ($res) {
        extract($res);
    }
}

if (empty($device)) {
    //设备扫描页面
    $tpl_data = Util::getTplData([$user, $account]);
    app()->scanPage($tpl_data);
}

//检查用户定位
if (App::isWxUser() && Util::mustValidateLocation($user, $device)) {
    
    $user->updateSettings('last.deviceId', '');

    //定位匹配成功后转跳网址
    $redirect = Util::murl(
        'entry',
        [
            'from' => 'location',
            'device' => $device->getShadowId(),
        ]
    );

    $tpl_data = Util::getTplData(
        [
            $user,
            $device,
            [
                'page.title' => '查找设备',
                'redirect' => $redirect,
            ],
        ]
    );

    //定位页面
    app()->locationPage($tpl_data);
}

if ($account)  {
    $res = Util::isAvailable($user, $account, $device);
    if (is_error($res)) {
        $user->remove('last');
        $account = null;        
    }
}

if (empty($account)) {
    //设置用户最后活动数据
    $user->setLastActiveData([
        'device' => $device->getId(),
        'ip' => CLIENT_IP,
        'time' => TIMESTAMP,
    ]);

    $tpl_data = Util::getTplData([$user, $device]);
    $tpl_data['from'] = $from;
    //设备首页
    //app()->devicePage($tpl_data);
    app()->douyinPage($device, $user);
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

$ticket_data = [
    'id' => Util::random(16),
    'time' => time(),
    'deviceId' => $device->getId(),
    'shadowId' => $device->getShadowId(),
    'accountId' => $account->getId(),
];

//准备领取商品的ticket
$user->updateSettings('last.ticket', $ticket_data);

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
