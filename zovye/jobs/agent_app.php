<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\agentApp;

defined('IN_IA') or exit('Access Denied');

//代理商申请提交

use zovye\CtrlServ;
use zovye\Helper;
use zovye\JobException;
use zovye\Log;
use zovye\model\agent_appModelObj;
use zovye\Request;
use function zovye\m;

$log = [
    'id' => Request::int('id'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

/** @var agent_appModelObj $app */
$app = m('agent_app')->findOne(['id' => $log['id']]);

if (empty($app)) {
    throw new JobException('找不到这个申请记录！', $log);
}

$log['data'] = $app->getTplMsgData();
$log['result'] = Helper::sendSysTemplateMessageTo('auth', $log['data']);

Log::debug('agent_app', $log);
