<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data = [];

if (request::has('id')) {
    $device_type = DeviceTypes::get(request::int('id'));
    if (empty($device_type)) {
        JSON::fail('找不到这个设备型号！');
    }
    $agent = $device_type->getAgent();
    if ($agent) {
        $agent_data = $agent->getAgentData();
        if ($agent_data) {
            $tpl_data['agent_name'] = $agent_data['name'].' - '.$agent->getNickname();
        } else {
            $tpl_data['agent_name'] = $agent->getNickname();
        }
        $tpl_data['agent_mobile'] = $agent->getMobile();
        $tpl_data['agent_openid'] = $agent->getOpenid();
    } else {
        $tpl_data['agent_name'] = '';
        $tpl_data['agent_mobile'] = '';
        $tpl_data['agent_openid'] = '';
    }
    $tpl_data['device_type'] = DeviceTypes::format($device_type);

    if ($tpl_data['device_type']['cargo_lanes']) {
        foreach ($tpl_data['device_type']['cargo_lanes'] as &$item) {
            $goods = Goods::get($item['goods']);
            if ($goods) {
                $item['goods_profile'] = Goods::format($goods, true, true);
            }
        }
    }
}

$content = app()->fetchTemplate('web/device_types/edit', $tpl_data);

JSON::success([
    'title' => isset($device_type) ? '编辑设备型号' : '添加设备型号',
    'content' => $content,
]);