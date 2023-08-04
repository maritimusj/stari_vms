<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\agentApp;

defined('IN_IA') or exit('Access Denied');

//代理商申请提交

use zovye\Config;
use zovye\CtrlServ;
use zovye\JobException;
use zovye\Log;
use zovye\model\agent_appModelObj;
use zovye\Request;
use zovye\User;
use zovye\Wx;
use function zovye\m;

$op = Request::op('default');
$log = [
    'id' => Request::int('id'),
];

if ($op == 'agent_app' && CtrlServ::checkJobSign($log)) {

    $tpl_id = Config::WxPushMessage('config.sys.tpl_id');
    if (empty($tpl_id)) {
        throw new JobException('没有配置模板消息id！', $log);
    } else {
        $log['tpl_id'] = $tpl_id;
    }

    $user_id = Config::WxPushMessage('config.sys.auth.user.id', 0);
    if (empty($user_id)) {
        throw new JobException('没有指定代理审核管理员！', $log);
    }

    $user = User::get($user_id);
    if (empty($user)) {
        throw new JobException('找不到指定代理审核管理员！', $log);
    }

    $log['admin'] = $user->profile();

    /** @var agent_appModelObj $app */
    $app = m('agent_app')->findOne(['id' => $log['id']]);

    if (empty($app)) {
        throw new JobException('找不到这个申请记录！', $log);
    }

    $data = $app->getTplMsgData();

    $log['data'] = $data;

    $log['result'] = Wx::sendTemplateMsg([
        'touser' => $user->getOpenid(),
        'template_id' => $tpl_id,
        'data' => $data,
    ]);
} else {
    $log['error'] = '签名不正确！';
}

Log::debug('agent_app', $log);
