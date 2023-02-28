<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$user = User::get(request::int('id'));
if (empty($user)) {
    JSON::fail('没有找到这个用户！');
}

$content = app()->fetchTemplate(
    'web/common/balance_edit',
    [
        'user' => [
            'id' => $user->getId(),
            'openid' => $user->getOpenid(),
            'nickname' => $user->getNickname(),
            'avatar' => $user->getAvatar(),
        ],
    ]
);

JSON::success(['title' => '调整用户<b>积分</b>', 'content' => $content]);