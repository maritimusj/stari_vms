<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$type = Request::int('type', Advertising::SCREEN);

$ad = Advertising::get($id, $type);

if (empty($ad)) {
    Response::toast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $type]), 'error');
}

$adv = [
    'id' => $ad->getId(),
    'state' => intval($ad->getState()),
    'agentId' => intval($ad->getAgentId()),
    'type' => intval($ad->getType()),
    'type_formatted' => Advertising::desc(intval($ad->getType())),
    'title' => strval($ad->getTitle()),
    'createtime_formatted' => date('Y-m-d H:i:s', $ad->getCreatetime()),
];

if ($ad->getType() == Advertising::SCREEN) {
    $media_data = Advertising::getMediaData();
    $media = $ad->getExtraData('media');
    $adv['media'] = "{$media_data[$media]['title']}";
    $adv['type_formatted'] .= "({$adv['media']})";
}

$assigned = $ad->settings('assigned', []);
$assigned = isEmptyArray($assigned) ? [] : $assigned;

Response::showTemplate('web/adv/assign', [
    'adv' => $adv,
    'multi_mode' => settings('advs.assign.multi') ? 'true' : '',
    'assign_data' => json_encode($assigned),
    'agent_url' => $this->createWebUrl('agent'),
    'group_url' => $this->createWebUrl('device', array('op' => 'group')),
    'tag_url' => $this->createWebUrl('tags'),
    'device_url' => $this->createWebUrl('device'),
    'save_url' => $this->createWebUrl('adv', array('op' => 'saveAssignData')),
    'back_url' => $this->createWebUrl('adv', ['type' => $ad->getType()]),
]);