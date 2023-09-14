<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\newAgent;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\domain\Agent;
use zovye\domain\Goods;
use zovye\JobException;
use zovye\Log;
use zovye\model\goodsModelObj;
use zovye\Request;

//代理申请通过后处理

$log = [
    'id' => Request::int('id'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$agent = Agent::get($log['id']);
if ($agent) {
    $query = Goods::query(['agent_id' => 0, 'sync' => 1]);
    $log['goods'] = [];
    /** @var goodsModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $log['goods'][] = Goods::CopyToAgent($agent->getId(), $entry);
    }
}

Log::debug('new_agent', $log);
