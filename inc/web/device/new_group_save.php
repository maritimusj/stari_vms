<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\device_groupsModelObj;

$title = Request::trim('title');
$clr = Request::trim('clr');
if (empty($clr)) {
    $clr = Util::randColor();
}

$agent_id = Request::int('agentId');

$id = Request::int('id');

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
    Response::toast('保存成功！', Util::url('device', ['op' => 'new_group']), 'success');
}

Response::toast('保存失败！', Util::url('device', ['op' => 'new_group']), 'error');