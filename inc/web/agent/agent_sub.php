<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\agent_vwModelObj;

$tpl_data = [
    'user_state_class' => [
        0 => 'normal',
        1 => 'banned',
    ],
];

$agents = [
    'list' => [],
];

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 10);

$sup_agent = Agent::get(Request::int('id'));

$agent_ids = Agent::getAllSubordinates($sup_agent);
if (!empty($agent_ids)) {
    $query = Principal::agent(['id' => $agent_ids]);
    //搜索用户名
    $referral_enabled = App::isAgentReferralEnabled();
    $total = $query->count();

    if ($total > 0) {
        $query->page($page, $page_size);
        $query->orderBy('id desc');

        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        /** @var  agent_vwModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'openid' => $entry->getOpenid(),
                'nickname' => strval($entry->getNickname()),
                'avatar' => strval($entry->getAvatar()),
                'mobile' => strval($entry->getMobile()),
                'state' => intval($entry->getState()),
                'total' => intval($entry->getDeviceTotal()),
                'commission_enabled' => App::isCommissionEnabled() && $entry->isCommissionEnabled(),
            ];

            $agent_data = $entry->get('agentData', []);
            $data['agentData'] = $agent_data;
            $data['partners'] = $agent_data['partners'] ? count($agent_data['partners']) : 0;
            $data['superior'] = $entry->getSuperior();
            $data['createtime'] = date('Y-m-d H:i:s', $entry->getCreatetime());
            $total = Stats::getMonthTotal($entry);
            $data['m'] = [
                'free' => intval($total['free']),
                'pay' => intval($total['pay']),
                'total' => intval($total['total']),
            ];
            if ($data['commission_enabled']) {
                $data['commission'] = [
                    'total' => $entry->getCommissionBalance()->total(),
                ];
            }

            if ($referral_enabled) {
                $referral = $entry->getReferral();
                if ($referral) {
                    $data['referral'] = $referral->getCode();
                }
            }
            $agents['list'][] = $data;
        }
    }
}

$tpl_data['agent_levels'] = Agent::getLevels();
$tpl_data['agents'] = $agents['list'];
$tpl_data['mobile_url'] = Util::murl('mobile');

$tpl_data['sup'] = $sup_agent->profile();

Response::showTemplate('web/agent/agent_sub', $tpl_data);