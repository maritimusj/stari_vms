<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Agent;
use zovye\domain\DeviceTypes;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$params = [
    'page' => Request::int('page'),
    'pagesize' => Request::int('pagesize'),
    'keywords' => Request::str('keywords'),
    'detail' => true,
];

$keywords = Request::trim('keywords', '', true);
if (!empty($keywords)) {
    $params['keywords'] = $keywords;
    $tpl_data['s_keywords'] = $keywords;
}

$agent_openid = Request::str('agent_openid');
if ($agent_openid) {
    if ($agent_openid == '-1') {
        $params['agent_id'] = 0;
        $tpl_data['s_agentId'] = 0;
    } else {
        $agent = Agent::get($agent_openid, true);
        if (empty($agent)) {
            Response::toast('找不到这个代理商！', Util::url('devicetypes'), 'error');
        }
        $params['agent_id'] = $agent->getId();
        $tpl_data['s_agent'] = $agent->profile();
        $tpl_data['s_agentId'] = $agent->getId();
    }
}

$result = DeviceTypes::getList($params);
if (is_error($result)) {
    Response::toast($result['message'], Util::url('devicetypes'), 'error');
}

$pager = We7::pagination($result['total'], $result['page'], $result['pagesize']);
if (stripos($pager, '&filter=1') === false) {
    $filter = [
        'agent_openid' => $agent_openid,
        'keywords' => $keywords,
        'filter' => 1,
    ];
    foreach ($filter as $index => $entry) {
        if (empty($entry)) {
            unset($filter[$index]);
        }
    }
    $params_str = http_build_query($filter);
    $pager = preg_replace('#href="(.*?)"#', 'href="${1}&'.$params_str.'"', $pager);
}

$tpl_data['device_types'] = $result['list'];
$tpl_data['first_type'] = settings('device.multi-types.first');
$tpl_data['pager'] = $pager;
$tpl_data['backer'] = $tpl_data['s_agent'] || $tpl_data['s_keywords'] || isset($tpl_data['s_agentId']);

Response::showTemplate('web/device_types/default', $tpl_data);