<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\newAgent;

use zovye\Agent;
use zovye\CtrlServ;
use zovye\Goods;
use zovye\request;
use zovye\model\goodsModelObj;
use zovye\Util;
use zovye\Wx;
use zovye\YZShop;
use function zovye\request;
use function zovye\settings;

//代理申请通过微信推送通知

$op = request::op('default');

$log = [
    'id' => request('id'),
];

if ($op == 'new_agent' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $id = request::int('id');
    $agent = Agent::get($id);
    if ($agent) {
        if (YZShop::isInstalled()) {
            YZShop::create($agent, $agent->getSuperior());
        }

        $tpl_id = settings('notice.agentresult_tplid');
        if ($tpl_id) {
            $agent_data = $agent->get('agentData', []);
            if ($agent_data) {
                $superior = $agent->getSuperior();

                if ($superior) {
                    $text = "{$superior->settings('agentData.name', 'n/a')}，{$superior->getMobile()}";
                }

                $data = [
                    'first' => ['value' => '恭喜，您的代理商申请已经通过审核！'],
                    'keyword1' => ['value' => $agent_data['license'] ?: '<无>'],
                    'keyword2' => ['value' => $agent_data['name'] ?: '<未填写>'],
                    'keyword3' => ['value' => $agent->getMobile()],
                    'keyword4' => ['value' => isset($text) ? $text : '<无>'],
                ];

                $res = Wx::sendTplNotice(
                    $agent->getOpenid(),
                    $tpl_id,
                    $data
                );

                $log['agent'] = $agent->getName();
                $log['data'] = $data;
                $log['result'] = $res;
            }
        }

        $query = Goods::query(['agent_id' => 0, 'sync' => 1]);
        $log['goods'] = [];
        /** @var goodsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $log['goods'][] = Goods::CopyToAgent($agent->getId(), $entry);
        }
    }

} else {
    $log['err'] = 'check sign failed!';
}

Util::logToFile('new_agent', $log);
