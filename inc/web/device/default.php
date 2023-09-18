<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Agent;
use zovye\domain\Device;
use zovye\domain\DeviceTypes;
use zovye\domain\Tags;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

if (Request::is_ajax()) {
    JSON::result(Device::search());
}

$tpl_data = [
    'lost_offset_day' => settings('device.lost', 1),
    'issuing_offset_day' => settings('device.issuing', 1),
];

//指定代理商
$agent_id = Request::int('agentId');
if ($agent_id) {
    $agent = Agent::get($agent_id);
    if (empty($agent)) {
        Response::toast('找不到这个代理商！', Util::url('device'), 'error');
    }
    $tpl_data['s_agent'] = $agent->profile();
}

$tags_id = Request::int('tag_id');
if ($tags_id) {
    $tag = Tags::get($tags_id);
    if (empty($tag)) {
        Response::toast('找不到这个标签！', Util::url('device'), 'error');
    }
    $tpl_data['s_tags'] = [
        [
            'id' => $tag->getId(),
            'title' => $tag->getTitle(),
            'count' => $tag->getCount(),
        ],
    ];
}

if (Request::has('types')) {
    $type_id = Request::int('types');
    $type = DeviceTypes::get($type_id);
    if (empty($type)) {
        Response::toast('找不到这个型号！', Util::url('device'), 'error');
    }
    $tpl_data['s_device_type'] = [
        [
            'id' => $type->getId(),
            'title' => $type->getTitle(),
            'lanes_total' => count($type->getCargoLanes()),
        ],
    ];
}

$tpl_data['page'] = Request::int('page', 1);
$tpl_data['upload'] = settings('device.upload.url', false);
$tpl_data['gate'] = CtrlServ::status();

Response::showTemplate('web/device/default_new', $tpl_data);