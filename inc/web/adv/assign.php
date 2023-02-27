<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$media_data = Advertising::getMediaData();

$id = request::int('id');
$type = request::int('type', Advertising::SCREEN);

$res = null;

if ($id > 0) {
    $res = Advertising::get($id, $type);
}

if (empty($res)) {
    Util::itoast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $type]), 'error');
}

$adv = [
    'id' => $res->getId(),
    'state' => intval($res->getState()),
    'agentId' => intval($res->getAgentId()),
    'type' => intval($res->getType()),
    'type_formatted' => Advertising::desc(intval($res->getType())),
    'title' => strval($res->getTitle()),
    'createtime_formatted' => date('Y-m-d H:i:s', $res->getCreatetime()),
];

if ($res->getType() == Advertising::SCREEN) {
    $media = $res->getExtraData('media');
    $adv['media'] = "{$media_data[$media]['title']}";
    $adv['type_formatted'] .= "({$adv['media']})";
}

$assigned = $res->settings('assigned', []);
$assigned = isEmptyArray($assigned) ? [] : $assigned;

app()->showTemplate('web/adv/assign', [
    'adv' => $adv,
    'multi_mode' => settings('advs.assign.multi') ? 'true' : '',
    'assign_data' => json_encode($assigned),
    'agent_url' => $this->createWebUrl('agent'),
    'group_url' => $this->createWebUrl('device', array('op' => 'group')),
    'tag_url' => $this->createWebUrl('tags'),
    'device_url' => $this->createWebUrl('device'),
    'save_url' => $this->createWebUrl('adv', array('op' => 'saveAssignData')),
    'back_url' => $this->createWebUrl('adv', ['type' => $res->getType()]),
]);