<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelObj;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
if ($op == 'default') {
    //分组表
    $query = Group::query(Group::CHARGING);

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    if (request::isset('agent_id')) {
        $agent_id = request::int('agent_id');
        if ($agent_id > 0) {
            $agent = Agent::get($agent_id);
            if (empty($agent)) {
                Util::itoast('找不到这个代理商！', '', 'error');
            }
            $query->where(['agent_id' => $agent_id]);
        } else {
            $query->where(['agent_id' => 0]);
        }
    }

    $total = $query->count();

    //列表数据
    $query->page($page, $page_size);

    $list = [];
    /** @var device_groupsModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = $entry->format();
        $agent = $entry->getAgent();
        if ($agent) {
            $data['agent'] = $agent->profile();
        }

        $data['total'] = Device::query(['group_id' => $entry->getId()])->count();
        $data['remote_version'] = ChargingServ::getGroupVersion($data['name']);

        $list[] = $data;
    }

    $tpl_data['list'] = $list;
    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
    $tpl_data['agentId'] = $agent_id ?? null;

    app()->showTemplate('web/charging/default', $tpl_data);

} elseif ($op == 'add' || $op == 'edit') {

    $tpl_data = [
        'op' => $op,
        'lbs_key' => settings('user.location.appkey', DEFAULT_LBS_KEY),
    ];

    if ($op == 'edit') {
        $id = request::int('id');
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

} elseif ($op == 'save') {

    $agent_id = request::int('agent_id');
    if ($agent_id) {
        $agent = Agent::get($agent_id);
        if (!$agent) {
            Util::itoast('找不到这个代理商！', '', 'error');
        }
    }

    $fee = [
        'l0' => [
            'ef' => request::float('l0ef'),
            'sf' => request::float('l0sf'),
        ],
        'l1' => [
            'ef' => request::float('l1ef'),
            'sf' => request::float('l1sf'),
        ],
        'l2' => [
            'ef' => request::float('l2ef'),
            'sf' => request::float('l2sf'),
        ],
        'l3' => [
            'ef' => request::float('l3ef'),
            'sf' => request::float('l3sf'),
        ],
        'ts' => array_map(function ($e) {
            return intval($e);
        }, request::array('ts', [])),
    ];

    $id = request::int('id');

    $lng = request::float('lng');
    $lat = request::float('lat');

    if ($id) {
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            Util::itoast('找不到指定的分组！', '', 'error');
        }
        if (isset($agent)) {
            $group->setAgentId($agent->getId());
        }

        $group->setClr(request::trim('clr'));
        $group->setAddress(request::trim('address'));
        $group->setTitle(request::trim('title'));
        $group->setDescription(request::trim('description'));
        $group->setLoc([
            'lng' => $lng,
            'lat' => $lat,
        ]);
        $group->setFee($fee);
    } else {
        $data = [
            'agent_id' => isset($agent) ? $agent->getId() : 0,
            'type_id' => Group::CHARGING,
            'title' => request::trim('title'),
            'clr' => request::trim('clr'),
            'extra' => [
                'name' => App::uid(6).'-'.Util::random(16),
                'description' => request::trim('description'),
                'address' => request::trim('address'),
                'lng' => $lng,
                'lat' => $lat,
                'fee' => $fee,
            ],
        ];

        $group = Group::create($data);
    }

    if ($group && $group->save()) {
        $res = ChargingServ::createOrUpdateGroup($group);
        if (is_error($res)) {
            Log::error('group', $res);
        } else {
            $group->setVersion($res);
            $group->save();            
        }

        $tbname = We7::tablename(device_groupsModelObj::getTableName(modelObj::OP_WRITE));
        $sql = sprintf("UPDATE %s SET `loc` = ST_GeomFromText('POINT(%f %f)') WHERE `id`=%d", $tbname, $lng, $lat, $group->getId());
        if (!We7::pdo_run($sql)) {
            Util::itoast(
                '更新位置出错！',
                Util::url('charging', ['op' => 'edit', 'id' => $group->getId()]),
                'error'
            );
        }
        Util::itoast(
            $id ? '保存成功！' : '创建成功！',
            Util::url('charging', ['op' => 'edit', 'id' => $group->getId()]),
            'success'
        );
    }

    Util::itoast(
        $id ? '保存失败！' : '创建失败！',
        Util::url('charging', $group ? ['op' => 'edit', 'id' => $group->getId()] : []),
        'error'
    );

} elseif ($op == 'remove') {

    $id = request::int('id');

    $result = Util::transactionDo(function () use ($id) {
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            return err('找不到指定的分组！');
        }

        $name = $group->getName();

        if ($group->destroy()) {
            $result = Device::query(['group_id' => $id])->findAll();

            /** @var deviceModelObj $entry */
            foreach ($result as $entry) {
                $entry->setGroupId(0);
            }
        }

        return ChargingServ::removeGroup($name);
    });

    if (is_error($result)) {
        Util::itoast($result['message'], Util::url('charging'), 'error');
    }

    Util::itoast('已删除！', Util::url('charging'), 'success');


} elseif ($op == 'refresh') {

    $groups = [];
    if (request::has('id')) {
        $id = request::int('id');
        $group = Group::get($id, Group::CHARGING);
        if (!$group) {
            Util::itoast('找不到这个分组！', Util::url('charging'), 'error');
        }
        $groups[] = $group;
    } else {
        foreach (Group::query(Group::CHARGING)->findAll() as $group) {
            $groups[] = $group;
        }
    }

    foreach ($groups as $group) {
        $res = ChargingServ::createOrUpdateGroup($group);
        if (is_error($res)) {
            Log::error('group', $res);
            continue;
        }
        $group->setVersion($res);
        $group->save();
    }

    Util::itoast('已更新！', Util::url('charging'), 'success');

} elseif ($op == 'charger') {

    $id = request::int('id');
    $device = Device::get($id);
    if (!$device || !$device->isChargingDevice()) {
        JSON::fail('设备不正确！');
    }

    $chargerNum = $device->getChargerNum();

    $result = [];

    $spanFN = function ($str) {
        return '<span class="val">'.$str.'</span>';
    };

    for ($i = 0; $i < $chargerNum; $i++) {
        $chargerData = $device->getChargerData($i + 1);

        $data = [
            'status' => 'unknown',
            'properties' => [],
            'errors' => $chargerData['errorBits'] || [],
        ];

        $status = '未知';
        switch ($chargerData['status']) {
            case 0:
                $status = '<span class="title">离线</span>';
                $data['status'] = 'offline';
                break;
            case 1:
                $status = '<span class="title">故障</span>';
                $data['status'] = 'malfunction';
                break;
            case 2:
                $status = '<span class="title">空闲</span>';
                $data['status'] = 'idle';
                break;
            case 3:
                $status = '<span class="title">充电中</span> ';
                $data['status'] = 'charging';
                break;
        }

        $data['properties'][] = [
            'title' => '状态',
            'val' => '<div class="charger-status operate">'.$status.'<div>',
        ];

        $parked = '未知';
        switch ($chargerData['parked']) {
            case 0:
                $parked = '否';
                break;
            case 1:
                $parked = '是';
                break;
        }
        $data['properties'][] = [
            'title' => '枪是否归位',
            'val' => $parked,
        ];

        $plugged = '未知';
        switch ($chargerData['plugged']) {
            case 0:
                $plugged = '否';
                break;
            case 1:
                $plugged = '是';
                break;
        }
        $data['properties'][] = [
            'title' => '是否插枪',
            'val' => $plugged,
        ];
        $data['properties'][] = [
            'title' => '输出电压',
            'val' => $spanFN(floatval($chargerData['outputVoltage'])).'V',
        ];
        $data['properties'][] = [
            'title' => '输出电流',
            'val' => $spanFN(floatval($chargerData['outputCurrent'])).'A',
        ];
        $data['properties'][] = [
            'title' => '枪线编码',
            'val' => strval($chargerData['chargerWireUID']),
        ];
        $data['properties'][] = [
            'title' => '枪线温度',
            'val' => $spanFN(floatval($chargerData['chargerWireTemp'])).'°C',
        ];
        $data['properties'][] = [
            'title' => 'SOC',
            'val' => $spanFN(intval($chargerData['soc'])).'%',
        ];
        $data['properties'][] = [
            'title' => '电池组最高温度',
            'val' => $spanFN(floatval($chargerData['batteryMaxTemp'])).'°C',
        ];
        $data['properties'][] = [
            'title' => '累计充电时间',
            'val' => $spanFN(intval($chargerData['timeTotal'])).'分',
        ];
        $data['properties'][] = [
            'title' => '剩余时间',
            'val' => $spanFN(intval($chargerData['timeRemain'])).'分',
        ];
        $data['properties'][] = [
            'title' => '充电度数',
            'val' => $spanFN(floatval($chargerData['chargedKWH'])).'kW·h',
        ];
        $data['properties'][] = [
            'title' => '已充金额',
            'val' => $spanFN(floatval($chargerData['priceTotal'])).'元',
        ];
        $data['properties'][] = [
            'title' => '硬件故障',
            'val' => intval($chargerData['error']),
        ];

        $result[] = $data;
    }

    JSON::success($result);

}