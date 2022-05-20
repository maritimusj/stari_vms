<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\agentApp;

//代理商申请提交

use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\model\agent_appModelObj;
use zovye\request;
use zovye\User;
use zovye\We7;
use zovye\Wx;
use function zovye\is_error;
use function zovye\m;
use function zovye\request;
use function zovye\settings;

$op = request::op('default');
$log = [
    'id' => request('id'),
];
if ($op == 'agent_app' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $tpl_id = settings('notice.agentReq_tplid');
    if ($tpl_id) {
        /** @var agent_appModelObj $app */
        $app = m('agent_app')->findOne(We7::uniacid(['id' => request::int('id')]));
        if ($app) {
            $notify_data = $app->getTplMsgData();
            if (settings('notice.authorizedAdminUserId')) {
                $query = User::query(['id' => settings('notice.authorizedAdminUserId')]);
                $user = $query->findOne();
                if ($user) {
                    if (!is_error(Wx::sendTplNotice($user->getOpenid(), $tpl_id, $notify_data))) {
                        $log['result'][$user->getOpenid()] = "[ {$user->getNickname()} ]=> Ok ".PHP_EOL;
                    } else {
                        $log['result'][$user->getOpenid()] = "[ {$user->getNickname()} ]=> fail ".PHP_EOL;
                    }
                } else {
                    $log['result']['error'] = '找不到指定的用户！';
                }
            } else {
                $log['result']['error'] = '没有指定用户！';
            }
            $log['data'] = $notify_data;
            Log::debug('agent_app', $log);
            Job::exit();
        }
    }
}

$log['result'] = 'fail';
Log::debug('agent_app', $log);
