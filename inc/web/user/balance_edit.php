<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\User;

defined('IN_IA') or exit('Access Denied');

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('没有找到这个用户！');
}

Response::templateJSON(
    'web/common/balance_edit',
    '调整用户<b>积分</b>',
    [
        'user' => [
            'id' => $user->getId(),
            'openid' => $user->getOpenid(),
            'nickname' => $user->getNickname(),
            'avatar' => $user->getAvatar(),
        ],
    ]
);