<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\agentMsg;

//转发代理商申请到指定代理商

use zovye\Agent;
use zovye\AgentApp;
use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\model\agent_appModelObj;
use zovye\Request;
use zovye\Util;
use zovye\We7;
use zovye\Wx;
use function zovye\m;
use function zovye\request;
use function zovye\settings;

$op = Request::op('default');
if ($op == 'forward_agent_app' && CtrlServ::checkJobSign(['id' => request('id'), 'agentIds' => request('agentIds')])) {

    $agent_ids = unserialize(urldecode(request('agentIds')));
    $tpl_id = settings('notice.agentReq_tplid');

    if ($tpl_id) {
        /** @var agent_appModelObj $app */
        $app = m('agent_app')->findOne(We7::uniacid(['id' => Request::int('id')]));
        if ($app) {
            $notify_data = $app->getTplMsgData();

            foreach ($agent_ids as $id) {
                $agent = Agent::get($id);
                if ($agent) {
                    $result = [];
                    foreach (Util::getNotifyOpenIds($agent, 'agentApp') as $openid) {

                        $res = Wx::sendTplNotice($openid, $tpl_id, $notify_data);
                        $result[] = [
                            'openid' => $openid,
                            'result' => $res,
                        ];
                    }

                    //记录转发消息推送结果
                    $app->set('forwardResult', $result);

                    $app->setState(AgentApp::FORWARD);
                    $app->save();
                }
            }

            Log::debug('forward_agent_app', 'forwardAgentApp => finished!');
            Job::exit();
        }
    }
}

Log::debug('forward_agent_app', 'forwardAgentApp => fail!');
