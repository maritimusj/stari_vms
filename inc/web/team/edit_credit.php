<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Team;

defined('IN_IA') or exit('Access Denied');

$member_id = Request::int('id');

$member = Team::getMember($member_id);
if (empty($member)) {
    JSON::fail('找不到这个车队成员！');
}

$user = $member->getAssociatedUser();
if (empty($user)) {
    JSON::fail('找不到这个车队成员关联的用户！');
}

Response::templateJSON(
    'web/user/credit',
    '透支额度',
    [
        'user' => $user->profile(),
        'val' => $user->getCredit(),
    ]
);
