<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\base\modelObj;
use zovye\model\device_groupsModelObj;

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
        'sf' => request::float('sf'),
    ],
    'l1' => [
        'ef' => request::float('l1ef'),
        'sf' => request::float('sf'),
    ],
    'l2' => [
        'ef' => request::float('l2ef'),
        'sf' => request::float('sf'),
    ],
    'l3' => [
        'ef' => request::float('l3ef'),
        'sf' => request::float('sf'),
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

    $tb_name = We7::tablename(device_groupsModelObj::getTableName(modelObj::OP_WRITE));
    $sql = sprintf("UPDATE %s SET `loc` = ST_GeomFromText('POINT(%f %f)') WHERE `id`=%d", $tb_name, $lng, $lat, $group->getId());
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
