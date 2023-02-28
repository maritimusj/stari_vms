<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\goodsClone;

//复制指定商品到所有代理商

use zovye\Agent;
use zovye\CtrlServ;
use zovye\Goods;
use zovye\Log;
use zovye\Request;
use function zovye\request;

$op = Request::op('default');

$log = [
    'id' => request('id'),
];

if ($op == 'goods_clone' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $goods = Goods::get(request('id'));
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
} else {
    $log['error'] = 'checkJobSign failed';
}

Log::debug('goods_clone', $log);