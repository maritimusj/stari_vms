<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\agentMsg;

//转发代理商申请到指定代理商

use zovye\Agent;
use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\model\msgModelObj;
use zovye\request;
use zovye\Util;
use zovye\We7;
use zovye\Wx;
use function zovye\m;
use function zovye\request;
use function zovye\settings;

$op = request::op('default');
$log = [
    'id' => request('id'),
];
if ($op == 'agent_msg' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $tpl_id = settings('agent.msg_tplid');
    if ($tpl_id) {
        /** @var msgModelObj $msg */
        $msg = m('msg')->findOne(We7::uniacid(['id' => request::int('id')]));
        if ($msg) {
            $notify_data = [
                'first' => ['value' => '尊敬的代理商，您有一条消息需要查阅！'],
                'keyword1' => ['value' => date('YmdHis')],
                'keyword2' => ['value' => $msg->getTitle()],
                'keyword3' => ['value' => '代理商通知'],
                'remark' => ['value' => '请及时登录代理商后台查看详细内容，谢谢合作！'],
            ];

            $agent_ids = $msg->get('agents', []);
            $data = We7::uniacid([
                'msg_id' => $msg->getId(),
                'title' => $msg->getTitle(),
                'content' => $msg->getContent(),
                'updatetime' => 0,
            ]);

            foreach ($agent_ids as $id) {
                $agent = Agent::get($id);
                if ($agent) {
                    $exists = m('agent_msg')->findOne(
                        We7::uniacid(['agent_id' => $agent->getId(), 'msg_id' => $msg->getId()])
                    );
                    if ($exists) {
                        continue;
                    }

                    foreach (Util::getNotifyOpenIds($agent, 'agentMsg') as $id => $openid) {
                        $data['agent_id'] = $id;
                        if (m('agent_msg')->create($data)) {
                            $log['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $notify_data);
                        }
                    }
                }
            }

            $log['data'] = $notify_data;
            Log::debug('agent_msg', $log);
            Job::exit();
        }
    }
}

$log['result'] = 'fail';
Log::debug('agent_msg', $log);
