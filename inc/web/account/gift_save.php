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
        'goods' => [],
    ],
    'enabled' => Request::bool('enabled', false),
];

$goods_ids = Request::array('goods');
$num_arr = Request::array('num');
foreach ($goods_ids as $index => $goods_id) {
    $goods = Goods::get($goods_id);
    if (empty($goods)) {
        Util::resultAlert('指定的商品不存在！', 'error');
    }
    $data['extra']['goods'][] = [
        'id' => $goods->getId(),
        'num' => intval($num_arr[$index] ?? 0),
    ];
}

$id = Request::int('id');
if ($id > 0) {
    $gift = FlashEgg::getGift($id);
    if (empty($gift)) {
        Util::resultAlert('找不到这个集蛋活动！', 'error');
    }

    $gift->setAgentId($data['agent_id']);
    $gift->setName($data['name']);
    $gift->setDescription($data['description']);
    $gift->setImage($data['image']);
    $gift->setExtraData($data['extra']);
    $gift->setEnabled($data['enabled']);

    if ($gift->save()) {
        Util::itoast('保存成功！', Util::url('account', ['op' => 'gift_edit', 'id' => $gift->getId()]), 'success');
    }

    Util::itoast('保存失败！', Util::url('account', ['op' => 'gift_edit', 'id' => $gift->getId()]), 'error');
} else {

    $gift = FlashEgg::createGift($data);

    if ($gift) {
        Util::itoast('创建成功！', Util::url('account', ['op' => 'gift_edit', 'id' => $gift->getId()]), 'success');
    }

    Util::itoast('创建失败！', Util::url('account', ['op' => 'gift_edit']), 'error');
}


