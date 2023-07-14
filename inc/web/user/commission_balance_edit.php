<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('没有找到这个用户！');
}

Response::templateJSON(
    'web/common/commission_balance_edit',
    '调整用户<b>余额</b>',
    [
        'user' => [
            'id' => $user->getId(),
            'openid' => $user->getOpenid(),
            'nickname' => $user->getNickname(),
            'avatar' => $user->getAvatar(),
            'isAgent' => $user->isAgent(),
            'isPartner' => $user->isPartner(),
            'isKeeper' => $user->isKeeper(),
            'verified' => $user->isIDCardVerified(),
        ],
    ]
);