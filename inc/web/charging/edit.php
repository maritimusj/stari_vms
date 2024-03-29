<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Group;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [
    'lbs_key' => settings('user.location.appkey', DEFAULT_LBS_KEY),
];

$id = Request::int('id');
if ($id > 0) {
    $group = Group::get($id, Group::CHARGING);
    if (!$group) {
        Response::toast('找不到这个分组！', Util::url('charging'), 'error');
    }
    $agent = $group->getAgent();
    if ($agent) {
        $tpl_data['agent'] = $agent->profile();
    }

    $tpl_data['id'] = $group->getId();
    $tpl_data['group'] = $group->format();
}

Response::showTemplate('web/charging/edit', $tpl_data);