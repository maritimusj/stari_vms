<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\agent_appModelObj;

$id = Request::int('id');
/** @var agent_appModelObj $app */
$app = m('agent_app')->findOne(We7::uniacid(['id' => $id]));
if ($app) {
    $state = $app->getState() != AgentApp::CHECKED ? AgentApp::CHECKED : AgentApp::WAIT;
    $app->setState($state);
    if ($app->save()) {
        Response::itoast('设置成功！', $this->createWebUrl('agent', ['op' => 'app']), 'success');
    }
}

Response::itoast('设置失败！', $this->createWebUrl('agent', ['op' => 'app']), 'error');