<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\device_groupsModelObj;

$title = request::trim('title');
$clr = request('clr');
$agent_id = request::int('agentId');

$id = request::int('id') ?: time();

/** @var device_groupsModelObj $one */
$one = Group::get($id);
if ($one) {
    $one->setTitle($title);
    $one->setClr($clr);
    $one->setAgentId($agent_id);
} else {
    $one = Group::create([
        'type_id' => Group::NORMAL,
        'agent_id' => $agent_id,
        'title' => $title,
        'clr' => $clr,
        'createtime' => time(),
    ]);
}

if ($one->save()) {
    Util::itoast('保存成功！', $this->createWebUrl('device', ['op' => 'new_group']), 'success');
}

Util::itoast('保存失败！', $this->createWebUrl('device', ['op' => 'new_group']), 'error');