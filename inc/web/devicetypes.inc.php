<?php

namespace zovye;

use zovye\model\device_typesModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

$tpl_data = [
    'op' => $op,
];

if ($op == 'default' || $op == 'device_types') {

    $params = [
        'page' => request::int('page'),
        'pagesize' => request::int('pagesize'),
        'keywords' => request::str('keywords'),
        'detail' => true,
    ];

    $keywords = trim(urldecode(request::trim('keywords')));
    if (!empty($keywords)) {
        $params['keywords'] = $keywords;
        $tpl_data['s_keywords'] = $keywords;
    }

    $agent_openid = request::str('agent_openid');
    if ($agent_openid) {
        if ($agent_openid == '-1') {
            $params['agent_id'] = 0;
            $tpl_data['s_agentId'] = 0;
        } else {
            $agent = Agent::get($agent_openid, true);
            if (empty($agent)) {
                Util::itoast('找不到这个代理商！', $this->createWebUrl('devicetypes'), 'error');
            }
            $params['agent_id'] = $agent->getId();
            $tpl_data['s_agent'] = $agent->profile();
            $tpl_data['s_agentId'] = $agent->getId();
        }
    }

    $result = DeviceTypes::getList($params);
    if (is_error($result)) {
        Util::itoast($result['message'], $this->createWebUrl('devicetypes'), 'error');
    }

    $pager = We7::pagination($result['total'], $result['page'], $result['pagesize']);
    if (stripos($pager, '&filter=1') === false) {
        $filter = [
            'agent_openid' => $agent_openid,
            'keywords' => $keywords,
            'filter' => 1,
        ];
        foreach ($filter as $index => $entry) {
            if (empty($entry)) {
                unset($filter[$index]);
            }
        }
        $params_str = http_build_query($filter);
        $pager = preg_replace('#href="(.*?)"#', 'href="${1}&' . $params_str . '"', $pager);
    }

    $tpl_data['device_types'] = $result['list'];
    $tpl_data['first_type'] = settings('device.multi-types.first');
    $tpl_data['pager'] = $pager;
    $tpl_data['backer'] = $tpl_data['s_agent'] || $tpl_data['s_keywords'] || isset($tpl_data['s_agentId']);

    app()->showTemplate('web/device-types/default', $tpl_data);

} elseif ($op == 'addDeviceTypes' || $op == 'editDeviceTypes') {

    if (request::has('id')) {
        $device_type = DeviceTypes::get(request::int('id'));
        if (empty($device_type)) {
            JSON::fail('找不到这个设备型号！');
        }
        $agent = $device_type->getAgent();
        if ($agent) {
            $agent_data = $agent->getAgentData();
            if ($agent_data) {
                $tpl_data['agent_name'] = $agent_data['name'] . ' - ' . $agent->getNickname();
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
            foreach($tpl_data['device_type']['cargo_lanes'] as &$item) {
                $goods = Goods::get($item['goods']);
                if ($goods) {
                    $item['goods_profile'] = Goods::format($goods, true, true);
                }
            }
        }        
    }

    $content = app()->fetchTemplate('web/device-types/edit', $tpl_data);

    JSON::success([
        'title' => $op == 'addDeviceTypes' ? '添加设备型号' : '编辑设备型号',
        'content' => $content,
    ]);

} elseif ($op == 'saveDeviceTypes') {

    $params = [];
    parse_str(request('params'), $params);

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

} elseif ($op == 'removeDeviceTypes') {

    $device_type = DeviceTypes::get(request('id'));
    if (empty($device_type)) {
        JSON::fail('找不到这个设备型号！');
    }

    $res = Util::transactionDo(
        function () use ($device_type) {
            $type_id = $device_type->getId();
            if ($device_type->destroy()) {
                return Device::removeDeviceType($type_id);
            }
            return error(State::ERROR, '失败');
        }
    );

    if (is_error($res)) {
        JSON::fail('删除失败！');
    }

    JSON::success('删除成功！');

} elseif ($op == 'setFirstType') {

    $type_id = request::int('id');
    $device_type = DeviceTypes::get($type_id);
    if (empty($device_type)) {
        JSON::fail('找不到这个设备型号！');
    }

    $firstType = settings('device.multi-types.first');
    if ($firstType == $type_id) {
        if (updateSettings('device.multi-types.first', 0)) {
            JSON::success(['msg' => '设置成功！', 'typeid' => 0]);
        }
    } else {
        if (updateSettings('device.multi-types.first', $type_id)) {
            JSON::success(['msg' => '设置成功！', 'typeid' => $type_id]);
        }
    }

    JSON::fail('保存失败！');

} elseif ($op == 'search') {

    $keywords = trim(urldecode(request::trim('keywords')));
    $params = [
        'keywords' => $keywords,
    ];
    $result = DeviceTypes::getList($params);
    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success($result);

} elseif ($op == 'searchGoods') {

    $keywords = trim(urldecode(request::trim('keywords')));
    if (empty($keywords)) {
        $res = Goods::getList(['page' => 1, 'pagesize' => 100, 'default_goods' => true]);
    } else {
        $params = [
            'keywords' => $keywords,
            'page' => 1, 
            'pagesize' => 100,
        ];
        $res = Goods::getList($params);
    }

    $id = request::trim('id');
    $res['id'] = $id;

    JSON::success($res);
}