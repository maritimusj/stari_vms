<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$commission_enabled = App::isCommissionEnabled();

$id = Request::int('id');
$account = Account::get($id);
if (empty($account)) {
    Response::itoast('这个任务不存在！', $this->createWebUrl('account'), 'error');
}

// if (App::isBalanceEnabled() && $account->getBonusType() == Account::BALANCE) {
//     Response::itoast('积分奖励的任务无法分配到指定设备！', $this->createWebUrl('account'), 'error');
// }

$data = [
    'id' => $account->getId(),
    'agentId' => $account->getAgentId(),
    'uid' => $account->getUid(),
    'clr' => $account->getClr(),
    'name' => $account->getName(),
    'title' => $account->getTitle(),
    'descr' => $account->getDescription(),
    'img' => $account->getImg(),
    'qrcode' => $account->getQrcode(),
];


if ($data['agentId']) {
    $agent = Agent::get($data['agentId']);
    if ($agent) {
        $data['agent'] = [
            'name' => $agent->getName(),
            'avatar' => $agent->getAvatar(),
        ];
    }
}

$assigned = $account->settings('assigned', []);
$assigned = isEmptyArray($assigned) ? [] : $assigned;

app()->showTemplate('web/account/assign', [
    'id' => $id,
    'commission_enabled' => $commission_enabled,
    'account' => $data,
    'assign_data' => json_encode($assigned),
]);