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
$app = AgentApplication::findOne(['id' => $id]);
if ($app) {
    $state = $app->getState() != AgentApplication::CHECKED ? AgentApplication::CHECKED : AgentApplication::WAIT;
    $app->setState($state);
    if ($app->save()) {
        Response::toast('设置成功！', Util::url('agent', ['op' => 'app']), 'success');
    }
}

Response::toast('设置失败！', Util::url('agent', ['op' => 'app']), 'error');