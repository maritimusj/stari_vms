<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\DeviceTypes;
use zovye\domain\Goods;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

if (Request::has('id')) {
    $device_type = DeviceTypes::get(Request::int('id'));
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

Response::templateJSON('web/device_types/edit', isset($device_type) ? '编辑设备型号' : '添加设备型号', $tpl_data);