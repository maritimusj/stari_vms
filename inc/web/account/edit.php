<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\MoscaleAccount;

$agent_name = '';
$agent_mobile = '';
$agent_openid = '';

$id = Request::int('id');
$account = Account::get($id);
if (empty($account)) {
    Util::itoast('任务不存在！', $this->createWebUrl('account'), 'error');
}

$type = $account->getType();

if ($account->getAgentId()) {
    $agent = Agent::get($account->getAgentId());
    if ($agent) {
        $agent_name = $agent->getNickname();
        $agent_mobile = $agent->getMobile();
        $agent_openid = $agent->getOpenid();
    }
}

$qr_codes = [];
$qrcode_data = $account->get('qrcodesData', []);
if ($qrcode_data && is_array($qrcode_data)) {
    foreach ($qrcode_data as $entry) {
        $qr_codes[] = $entry['img'];
    }
}

$limits = $account->get('limits', []);

$bonus_type = $account->getBonusType();
if ($bonus_type == Account::COMMISSION) {
    $amount = number_format($account->getCommissionPrice() / 100, 2);
} else {
    $amount = $account->getBalancePrice();
}

$config = $account->get('config', []);

$tpl_data = [
    'op' => 'edit',
    'type' => $type,
    'id' => $id,
    'account' => $account,
    'qrcodes' => $qr_codes ?? null,
    'limits' => $limits ?? null,
    'bonus_type' => $bonus_type,
    'amount' => $amount ?? 0,
    'balance' => $amount ?? 0,
    'agent_name' => $agent_name,
    'agent_mobile' => $agent_mobile,
    'agent_openid' => $agent_openid,
    'config' => $config,
    'from' => Request::str('from', 'base'),
];

if (App::isFlashEggEnabled() && $account->isFlashEgg()) {
    $tpl_data['goods'] = $account->getGoodsData(false);
    $tpl_data['media_type'] = $account->getMediaType();
}

if (App::isMoscaleEnabled() && $type == Account::MOSCALE) {
    $tpl_data['moscaleMachineKey'] = settings('moscale.fan.key', '');
    $tpl_data['moscaleLabelList'] = MoscaleAccount::getLabelList();
    $tpl_data['moscaleAreaListSaved'] = settings('moscale.fan.label', []);
    if (!is_array($tpl_data['moscaleAreaListSaved'])) {
        $tpl_data['moscaleAreaListSaved'] = [];
    }

    $tpl_data['moscaleRegionData'] = MoscaleAccount::getRegionData();
    $tpl_data['moscaleRegionSaved'] = settings('moscale.fan.region', []);
    if (!is_array($tpl_data['moscaleRegionSaved'])) {
        $tpl_data['moscaleRegionSaved'] = [];
    }
}

app()->showTemplate('web/account/edit_'.$type, $tpl_data);