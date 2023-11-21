<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Agent;
use zovye\domain\GSP;
use zovye\domain\PaymentConfig;
use zovye\domain\User;
use zovye\model\gsp_userModelObj;
use zovye\model\payment_configModelObj;
use zovye\util\Helper;
use zovye\util\Util;

$op = Request::op('default');

$id = Request::int('id');
$agent = null;
if ($op == 'create') {
    $res = User::get($id);
    if ($res) {
        if ($res->isAgent() || $res->isPartner()) {
            Response::toast('用户已经是代理商或者合伙人！', Util::url('agent'), 'error');
        }
        $agent = $res->agent();
    }
} else {
    $agent = Agent::get($id);
}

if (empty($agent)) {
    Response::toast('找不到这个代理商！', Util::url('agent'), 'error');
}

$agent_data = (array)$agent->get('agentData', []);

$agent_data['notice'] = Helper::getWxPushMessageConfig((array)$agent_data['notice']);

$superior = $agent->getSuperior();
$superior_data = $superior ? $superior->get('agentData') : null;

if (!isset($agent_data['location']['validate']['enabled'])) {
    setArray($agent_data, 'location.validate', [
        'enabled' => App::isLocationValidateEnabled(),
        'distance' => App::getUserLocationValidateDistance(1),
    ]);
}
if (!isset($agent_data['gsp.order'])) {
    $agent_data['gsp.order'] = [
        'f' => 1,
        'b' => 1,
        'p' => 1,
    ];
}

$tpl_data = [
    'op' => $op,
    'agent_levels' => Agent::getLevels(),
    'id' => $id,
    'agent' => $agent,
    'agent_data' => $agent_data,
    'superior' => $superior,
    'superior_data' => $superior_data,
];

if ($op == 'agent_commission') {
    $data = $agent->settings('agentData.gsp.users', []);
    $free_gsp_users = array_map(
        function ($openid, $entry) {
            $res = User::get($openid, true);
            if ($res) {
                $data = [
                    'id' => $res->getId(),
                    'nickname' => $res->getNickname(),
                    'avatar' => $res->getAvatar(),
                    'percent' => $entry['percent'],
                    'createtime_formatted' => date('Y-m-d H:i:s', $entry['createtime']),
                ];
                if ($entry['percent']) {
                    $data['gsp'] = [
                        'title' => '百分比%',
                        'val' => number_format($entry['percent'] / 100, 2).'%',
                    ];
                } elseif ($entry['percent/goods']) {
                    $data['gsp'] = [
                        'title' => '百分比% x 商品数量',
                        'val' => number_format($entry['percent/goods'] / 100, 2).'%',
                    ];
                } elseif ($entry['amount']) {
                    $data['gsp'] = [
                        'title' => '固定金额',
                        'val' => '¥'.number_format($entry['amount'] / 100, 2),
                    ];
                } elseif ($entry['amount/goods']) {
                    $data['gsp'] = [
                        'title' => '固定金额 x 商品数量',
                        'val' => '¥'.number_format($entry['amount/goods'] / 100, 2),
                    ];
                }
                $data['order_type'] = is_array($entry['order']) ? $entry['order'] : [
                    'f' => 1,
                    'b' => 1,
                    'p' => 1,
                ];
                if ($res->isGSPor()) {
                    $data['commission_balance_formatted'] = number_format(
                        $res->getCommissionBalance()->total() / 100,
                        2
                    );
                }

                return $data;
            }

            return [];
        },
        array_keys($data),
        $data
    );

    $tpl_data['free_gsp_users'] = $free_gsp_users;

    $mixed_gsp_users = [];
    $query = GSP::query(['agent_id' => $agent->getId()]);
    /** @var gsp_userModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $user = GSP::getUser($agent, $entry);
        if ($user) {
            $uid = $entry->getUid();
            $g = [
                'val_type' => $entry->getValType(),
                'val' => number_format($entry->getVal() / 100, 2),
            ];
            if (empty($mixed_gsp_users[$uid])) {
                $data = [
                    'id' => $entry->getId(),
                    'nickname' => $user->getNickname(),
                    'avatar' => $user->getAvatar(),
                ];

                if ($entry->isFreeOrderIncluded()) {
                    $data['f'] = $g;
                }
                if ($entry->isPayOrderIncluded()) {
                    $data['p'] = $g;
                }
                $mixed_gsp_users[$uid] = $data;
            } else {
                if ($entry->isFreeOrderIncluded()) {
                    $mixed_gsp_users[$uid]['f'] = $g;
                }
                if ($entry->isPayOrderIncluded()) {
                    $mixed_gsp_users[$uid]['p'] = $g;
                }
            }
        }
    }

    $tpl_data['mixed_gsp_users'] = $mixed_gsp_users;

} elseif ($op == 'agent_payment') {

    $tpl_data['payment'] = [];

    foreach ([Pay::LCSW, Pay::SQB, Pay::WX_V3] as $name) {
        /** @var payment_configModelObj $config */
        $config = PaymentConfig::findOne([
            'agent_id' => $agent->getId(),
            'name' => $name,
        ]);
        if ($config) {
            $tpl_data['payment'][$name] = $config->getExtraData();
        }
    }

} elseif ($op == 'agent_misc') {
    $tpl_data['themes'] = Theme::valid();
}

$keeper_data = $agent->settings('agentData.keeper.data', []);
if ($keeper_data) {
    if ($keeper_data['type'] == 'fixed') {
        $keeper_data['fixed'] = number_format($keeper_data['fixed'] / 100, 2, '.', '');
    } else {
        $keeper_data['percent'] = intval($keeper_data['percent']);
    }

    $tpl_data['keeper_data'] = $keeper_data;
}

Response::showTemplate('web/agent/edit', $tpl_data);