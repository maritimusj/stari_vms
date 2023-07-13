<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
    if ($msg) {
        $msg->destroy();
        Response::toast('删除成功！', $this->createWebUrl('agent', ['op' => 'msg']), 'success');
    }
}

Response::toast('删除失败！', $this->createWebUrl('agent', ['op' => 'msg']), 'error');