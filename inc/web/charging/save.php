<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\base\modelObj;
use zovye\model\device_groupsModelObj;

$agent_id = Request::int('agent_id');
if ($agent_id) {
    $agent = Agent::get($agent_id);
    if (!$agent) {
        Response::toast('找不到这个代理商！', '', 'error');
    }
}

$fee = [
    'l0' => [
        'ef' => Request::float('l0ef'),
        'sf' => Request::float('sf'),
    ],
    'l1' => [
        'ef' => Request::float('l1ef'),
        'sf' => Request::float('sf'),
    ],
    'l2' => [
        'ef' => Request::float('l2ef'),
        'sf' => Request::float('sf'),
    ],
    'l3' => [
        'ef' => Request::float('l3ef'),
        'sf' => Request::float('sf'),
    ],
    'ts' => array_map(function ($e) {
        return intval($e);
    }, Request::array('ts')),
];

$id = Request::int('id');

$lng = Request::float('lng');
$lat = Request::float('lat');

if ($id) {
    $group = Group::get($id, Group::CHARGING);
    if (empty($group)) {
        Response::toast('找不到指定的分组！', '', 'error');
    }
    if (isset($agent)) {
        $group->setAgentId($agent->getId());
    }

    $group->setClr(Request::trim('clr'));
    $group->setAddress(Request::trim('address'));
    $group->setTitle(Request::trim('title'));
    $group->setDescription(Request::trim('description'));
    $group->setLoc([
        'lng' => $lng,
        'lat' => $lat,
    ]);
    $group->setFee($fee);
} else {
    $data = [
        'agent_id' => isset($agent) ? $agent->getId() : 0,
        'type_id' => Group::CHARGING,
        'title' => Request::trim('title'),
        'clr' => Request::trim('clr'),
        'extra' => [
            'name' => App::uid(6).'-'.Util::random(16),
            'description' => Request::trim('description'),
            'address' => Request::trim('address'),
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
        Response::toast(
            '更新位置出错！',
            Util::url('charging', ['op' => 'edit', 'id' => $group->getId()]),
            'error'
        );
    }
    Response::toast(
        $id ? '保存成功！' : '创建成功！',
        Util::url('charging', ['op' => 'edit', 'id' => $group->getId()]),
        'success'
    );
}

Response::toast(
    $id ? '保存失败！' : '创建失败！',
    Util::url('charging', $group ? ['op' => 'edit', 'id' => $group->getId()] : []),
    'error'
);
