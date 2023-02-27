<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data = [
    'lbs_key' => settings('user.location.appkey', DEFAULT_LBS_KEY),
];

$id = request::int('id');
if ($id > 0) {
    $group = Group::get($id, Group::CHARGING);
    if (!$group) {
        Util::itoast('找不到这个分组！', Util::url('charging'), 'error');
    }
    $agent = $group->getAgent();
    if ($agent) {
        $tpl_data['agent'] = $agent->profile();
    }

    $tpl_data['id'] = $group->getId();
    $tpl_data['group'] = $group->format();
}

app()->showTemplate('web/charging/edit', $tpl_data);