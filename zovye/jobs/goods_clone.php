<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\goodsClone;

defined('IN_IA') or exit('Access Denied');

//复制指定商品到所有代理商

use zovye\CtrlServ;
use zovye\domain\Agent;
use zovye\domain\Goods;
use zovye\JobException;
use zovye\Log;
use zovye\Request;

$log = [
    'id' => Request::int('id'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$goods = Goods::get(Request::int('id'));
if (empty($goods)) {
    $log['error'] = 'goods not exists!';
} else {
    $log['name'] = $goods->getName();
    $log['result'] = [];

    $query = Agent::query();
    foreach ($query->findAll() as $agent) {
        $goods_query = Goods::query(['agent_id' => $agent->getId()]);
        $exists = false;
        foreach ($goods_query->findAll() as $g) {
            if ($g->settings('extra.clone.original') == $goods->getId()) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $log['result'][] = Goods::CopyToAgent($agent->getId(), $goods);
        }
    }
}

Log::debug('goods_clone', $log);