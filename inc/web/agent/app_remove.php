<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$app = m('agent_app')->findOne(We7::uniacid(['id' => $id]));

if ($app && $app->destroy()) {
    Response::toast('删除成功！', Util::url('agent', ['op' => 'app']), 'success');
}

Response::toast('删除失败！', Util::url('agent', ['op' => 'app']), 'error');