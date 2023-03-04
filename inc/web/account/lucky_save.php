<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$agent_id = Request::int('agent_id');
if ($agent_id) {
    $agent = Agent::get($agent_id);
    if (empty($agent)) {
        Util::resultAlert('找不到这个代理商！', 'error');
    }
}

$data = [
    'agent_id' => isset($agent) ? $agent->getId() : 0,
    'name' => Request::trim('name'),
    'description' => Request::trim('description'),
    'image' => Request::trim('image'),
    'extra' => [
        'remark' =>  Request::trim('remark'),
    ],
    'enabled' => Request::bool('enabled'),
];

$id = Request::int('id');
if ($id > 0) {
    $lucky = FlashEgg::getLucky($id);
    if (empty($lucky)) {
        Util::resultAlert('找不到这个抽奖活动！', 'error');
    }

    $lucky->setAgentId($data['agent_id']);
    $lucky->setName($data['name']);
    $lucky->setDescription($data['description']);
    $lucky->setImage($data['image']);
    $lucky->setExtraData($data['extra']);
    $lucky->setEnabled($data['enabled']);

    if ($lucky->save()) {
        Util::itoast('保存成功！', Util::url('account', ['op' => 'lucky_edit', 'id' => $lucky->getId()]), 'success');
    }

    Util::itoast('保存失败！', Util::url('account', ['op' => 'lucky_edit', 'id' => $lucky->getId()]), 'error');
} else {

    $lucky = FlashEgg::createlucky($data);

    if ($lucky) {
        Util::itoast('创建成功！', Util::url('account', ['op' => 'lucky_edit', 'id' => $lucky->getId()]), 'success');
    }

    Util::itoast('创建失败！', Util::url('account', ['op' => 'lucky_edit']), 'error');
}