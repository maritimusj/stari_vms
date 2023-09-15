<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//分配设备控件查询代理详情
use zovye\domain\Agent;
use zovye\domain\Principal;
use zovye\model\agent_vwModelObj;
use zovye\util\Helper;
use zovye\util\Util;

if (Request::is_ajax() && Request::has('id')) {
    $agent = Agent::get(Request::int('id'));
    if ($agent) {
        $data = [
            'id' => $agent->getId(),
            'nickname' => strval($agent->getNickname()),
            'avatar' => strval($agent->getAvatar()),
            'mobile' => strval($agent->getMobile()),
            'state' => intval($agent->getState()),
            'commission_enabled' => App::isCommissionEnabled() && $agent->isCommissionEnabled(),
        ];

        $data['agentData'] = [
            'area' => $agent->settings('agentData.area', []),
            'company' => $agent->settings('agentData.company', ''),
            'name' => $agent->settings('agentData.name', ''),
            'level' => $agent->settings('agentData.level'),
        ];

        JSON::success($data);
    }

    JSON::fail('没有找到这个代理商');
}

$tpl_data = [
    'user_state_class' => [
        0 => 'normal',
        1 => 'banned',
    ],
];

$agents = [
    'total' => 0,
    'page' => 1,
    'totalpage' => 1,
    'list' => [],
];

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 10);

$query = Principal::agent();

//搜索指定ID
$ids = Helper::parseIdsFromGPC();
if (!empty($ids)) {
    $query->where(['id' => $ids]);
}

//搜索用户昵称或者手机号码
$keywords = Request::trim('keywords');
if ($keywords) {
    $query->whereOr([
        'nickname LIKE' => "%$keywords%",
        'mobile LIKE' => "%$keywords%",
        'name LIKE' => "%$keywords%",
    ]);
}

$referral_enabled = App::isAgentReferralEnabled();
$total = $query->count();

if ($total > 0) {
    $total_page = ceil($total / $page_size);

    $agents['total'] = $total;
    $agents['totalpage'] = $total_page;
    $agents['page'] = $page;

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    /** @var  agent_vwModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'openid' => $entry->getOpenid(),
            'nickname' => $entry->getNickname(),
            'avatar' => $entry->getAvatar(),
            'mobile' => $entry->getMobile(),
            'state' => $entry->getState(),
            'total' => $entry->getDeviceTotal(),
            'commission_enabled' => App::isCommissionEnabled() && $entry->isCommissionEnabled(),
        ];

        if (Request::is_ajax()) {
            $level_data = Agent::getLevels($entry->settings('agentData.level'))?: '';
            $data['agentData'] = [
                'area' => $entry->settings('agentData.area', []),
                'company' => $entry->settings('agentData.company', ''),
                'name' => $entry->settings('agentData.name', ''),
                'level' => $level_data,
            ];
        } else {
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

if (Request::is_ajax()) {
    $agents['serial'] = Request::str('serial') ?: microtime(true).'';
    JSON::success($agents);
}

$tpl_data['page'] = $page;
$tpl_data['agents'] = $agents['list'];
$tpl_data['mobile_url'] = Util::murl('mobile');
$tpl_data['keywords'] = $keywords;
$tpl_data['agent_levels'] = Agent::getLevels();

Response::showTemplate('web/agent/default', $tpl_data);