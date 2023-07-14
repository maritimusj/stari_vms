<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (Request::is_ajax()) {
    JSON::result(Device::search());
}

$tpl_data = [
    'lost_offset_day' => intval(settings('device.lost', 1)),
    'issuing_offset_day' => intval(settings('device.issuing', 1)),
];

//指定代理商
$agent_id = Request::int('agentId');
if ($agent_id) {
    $agent = Agent::get($agent_id);
    if (empty($agent)) {
        Response::toast('找不到这个代理商！', $this->createWebUrl('device'), 'error');
    }
    $tpl_data['s_agent'] = $agent->profile();
}

$tags_id = Request::int('tag_id');
if ($tags_id) {
    $tag = m('tags')->findOne(['id' => $tags_id]);
    if (empty($tag)) {
        Response::toast('找不到这个标签！', $this->createWebUrl('device'), 'error');
    }
    $tpl_data['s_tags'] = [
        [
            'id' => intval($tag->getId()),
            'title' => strval($tag->getTitle()),
            'count' => intval($tag->getCount()),
        ],
    ];
}

if (Request::has('types')) {
    $type_id = Request::int('types');
    $type = DeviceTypes::get($type_id);
    if (empty($type)) {
        Response::toast('找不到这个型号！', $this->createWebUrl('device'), 'error');
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
$tpl_data['upload'] = (bool)settings('device.upload.url', '');
$tpl_data['gate'] = CtrlServ::status();

Response::showTemplate('web/device/default_new', $tpl_data);