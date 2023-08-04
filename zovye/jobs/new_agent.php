<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\newAgent;

defined('IN_IA') or exit('Access Denied');

use zovye\Agent;
use zovye\CtrlServ;
use zovye\Goods;
use zovye\Log;
use zovye\model\goodsModelObj;
use zovye\Request;

//代理申请通过后处理

$op = Request::op('default');

$log = [
    'id' => Request::int('id'),
];

if ($op == 'new_agent' && CtrlServ::checkJobSign($log)) {
    $agent = Agent::get($log['id']);
    if ($agent) {
        $query = Goods::query(['agent_id' => 0, 'sync' => 1]);
        $log['goods'] = [];
        /** @var goodsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $log['goods'][] = Goods::CopyToAgent($agent->getId(), $entry);
        }
    }

} else {
    $log['err'] = '签名检验失败！';
}

Log::debug('new_agent', $log);
