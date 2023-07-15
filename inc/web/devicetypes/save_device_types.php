<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\device_typesModelObj;

$params = [];
parse_str(Request::str('params'), $params);

$title = trim($params['title']);
if (empty($title)) {
    JSON::fail('型号名称不能为空！');
}

if (empty($params['goods']) || empty($params['capacities'])) {
    JSON::fail('至少需要一个默认货道！');
}

if ($params['agentId']) {
    $agent = Agent::get($params['agentId'], true);
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }
}

if ($params['id']) {
    /** @var device_typesModelObj $device_type */
    $device_type = DeviceTypes::get($params['id']);
    if (empty($device_type)) {
        JSON::fail('找不到这个设备型号！');
    }
    if ($title != $device_type->getTitle()) {
        $device_type->setTitle($title);
    }
    $agent_id = !empty($agent) ? $agent->getId() : 0;
    $device_type->setAgentId($agent_id);

    $cargo_lanes = [];
    foreach ($params['goods'] as $index => $goods_id) {
        $cargo_lanes[] = [
            'goods' => intval($goods_id),
            'capacity' => intval($params['capacities'][$index]),
        ];
    }

    $device_type->setExtraData('cargo_lanes', $cargo_lanes);

} else {
    $data = [
        'title' => $title,
        'agent_id' => !empty($agent) ? $agent->getId() : 0,
        'extra' => [
            'cargo_lanes' => [],
        ],
    ];
    foreach ($params['goods'] as $index => $goods_id) {
        $data['extra']['cargo_lanes'][] = [
            'goods' => intval($goods_id),
            'capacity' => intval($params['capacities'][$index]),
        ];
    }

    $device_type = DeviceTypes::create($data);
}

if ($device_type && $device_type->save()) {
    JSON::success('保存设备型号成功！');
}

JSON::fail('保存设备型号失败！');