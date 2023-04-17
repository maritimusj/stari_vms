<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\keeperModelObj;
use zovye\model\userModelObj;

$tpl_data = [
    'user_state_class' => [
        0 => 'normal',
        1 => 'banned',
    ],
];

$tpl_data['agent_levels'] = settings('agent.levels');

$tpl_data['commission_enabled'] = App::isCommissionEnabled();
$tpl_data['balance_enabled'] = App::isBalanceEnabled();

$credit_used = settings('we7credit.enabled');

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

//身份
$s_principal = Request::trim('s_principal');
if ($s_principal) {
    switch ($s_principal) {
        case 'agent':
            $query = Principal::agent();
            break;
        case 'partner':
            $query = Principal::partner();
            break;
        case 'keeper':
            $query = Principal::keeper();
            break;
        case 'tester':
            $query = Principal::tester();
            break;
        case 'gspor':
            $query = Principal::gspor();
            break;
    }
}

if (empty($query)) {
    $query = User::query(['app <>' => User::PSEUDO]);
}

//搜索用户名
$s_keywords = urldecode(Request::trim('s_keywords'));
if ($s_keywords) {
    if (in_array($s_principal, ['agent', 'partner', 'keeper', 'tester', 'gspor'])) {
        $query->whereOr([
            'name LIKE' => "%$s_keywords%",
            'nickname LIKE' => "%$s_keywords%",
            'mobile LIKE' => "%$s_keywords%",
            'openid LIKE' => "%$s_keywords%",
        ]);
    } else {
        $query->whereOr([
            'nickname LIKE' => "%$s_keywords%",
            'mobile LIKE' => "%$s_keywords%",
            'openid LIKE' => "%$s_keywords%",
        ]);
    }
}

$types = [];
$s_type_wx = Request::bool('s_type_wx');
if ($s_type_wx) {
    $types[] = User::WX;
}

$s_type_wxapp = Request::bool('s_type_wxapp');
if ($s_type_wxapp) {
    $types[] = User::WxAPP;
}

$s_type_ali = Request::bool('s_type_ali');
if ($s_type_ali) {
    $types[] = User::ALI;
}

$s_type_douyin = Request::bool('s_type_douyin');
if ($s_type_douyin) {
    $types[] = User::DouYin;
}

$s_type_api = Request::bool('s_type_api');
if ($s_type_api) {
    $types[] = User::API;
}

$s_type_third = Request::bool('s_type_third');
if ($s_type_third) {
    $types[] = User::THIRD_ACCOUNT;
}

//当指定了**部分**用户类型时，加入用户app条件过滤
if ($types && count($types) < 3) {
    $query->where(['app' => $types]);
}

$total = $query->count();

$query->page($page, $page_size);

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->orderBy('id desc');

$charging_device_enabled = App::isChargingDeviceEnabled();
$fueling_device_enabled = App::isFuelingDeviceEnabled();

$users = [];
/** @var  userModelObj $user */
foreach ($query->findAll() as $user) {
    $data = [
        'id' => $user->getId(),
        'openid' => $user->getOpenid(),
        'nickname' => $user->getNickname(),
        'sex' => $user->settings('fansData.sex'),
        'avatar' => $user->getAvatar(),
        'createtime' => date('Y-m-d H:i:s', $user->getCreatetime()),
        'mobile' => $user->getMobile(),
        'state' => $user->getState(),
        'banned' => $user->isBanned(),
        'isAgent' => $user->isAgent(),
        'isPartner' => $user->isPartner(),
        'isKeeper' => $user->isKeeper(),
        'isTester' => $user->isTester(),
        'isGSPor' => $user->isGSPor(),
        'verified' => $user->isIDCardVerified(),
    ];

    if ($credit_used) {
        $data['credit'] = $user->getWe7credit()->total();
    }

    if ($user->isAgent()) {
        $agent = $user->agent();
        $data['agent'] = $agent->getAgentLevel();

    } elseif ($user->isPartner()) {
        $agent = $user->getPartnerAgent();
        if ($agent) {
            $data['co_agent'] = $agent->profile();
        }
    } elseif ($user->isKeeper()) {
        /** @var keeperModelObj $keeper */
        $keeper = $user->getKeeper();
        if ($keeper) {
            $agent = $keeper->getAgent();
            if ($agent) {
                $data['co_agent'] = $agent->profile();
            }
        }
    }

    //用户来源信息
    $from_data = $user->get('fromData', []);
    if ($from_data) {
        if (!empty($from_data['device'])) {
            $data['from'] = "来自设备：{$from_data['device']['name']}";
        } elseif (!empty($from_data['account'])) {
            $data['from'] = "来自公众号：{$from_data['account']['name']}，{$from_data['account']['title']}";
        }
    }

    if (App::isUserVerify18Enabled()) {
        $data_arr = $user->settings('verify_18');
        if ($data_arr['verify']) {
            $data['verified'] = 1;
        }
    }

    $data['type'] = User::getUserCharacter($user);

    if ($charging_device_enabled) {
        $data['charging'] = $user->chargingNOWData();
    }

    if ($fueling_device_enabled) {
        $data['fueling'] = $user->fuelingNOWData();
    }

    if (App::isFlashEggEnabled()) {
        $gifts = $user->settings('flash_gift', []);
        if (!isEmptyArray($gifts)) {
            $data['flash_gifts'] = count($gifts);
        }
    }

    $users[] = $data;
}

$tpl_data['s_keywords'] = $s_keywords;
$tpl_data['s_type_wx'] = $s_type_wx;
$tpl_data['s_type_wxapp'] = $s_type_wxapp;
$tpl_data['s_type_ali'] = $s_type_ali;
$tpl_data['s_type_douyin'] = $s_type_douyin;
$tpl_data['s_type_api'] = $s_type_api;
$tpl_data['s_type_third'] = $s_type_third;
$tpl_data['s_principal'] = $s_principal;
$tpl_data['backer'] = $s_keywords || $s_type_wx || $s_type_wxapp || $s_type_ali || $s_type_douyin || $s_type_api || $s_type_third;

$tpl_data['users'] = $users;

app()->showTemplate('web//user/default', $tpl_data);