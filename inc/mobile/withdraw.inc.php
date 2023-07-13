<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//用户参数
$params = [
    'update' => true,
    'create' => true,
    'from' => [
        'src' => 'mobile',
        'ip' => CLIENT_IP,
        'user-agent' => $_SERVER['HTTP_USER_AGENT'],
    ],
];

$user = Util::getCurrentUser($params);
if (empty($user)) {
    Response::alert('只能从微信中打开，谢谢！', 'error');
}

$op = Request::op('default');

if ($op == 'default') {

    if (!$user->isGSPor()) {
        Response::alert('用户未开通余额账户！', 'error');
    }

    $balance = $user->getCommissionBalance()->total();

    app()->showTemplate('gspor', [
        'balance' => $balance,
        'balance_formatted' => number_format($balance / 100, 2),
    ]);

} elseif ($op == 'req') {

    app()->showTemplate('withdraw');

} elseif ($op == 'logs') {

    app()->showTemplate('record');
}