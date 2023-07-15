<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = Request::op();

$tpl_data = [
    'op' =>$op,
];

$id = Request::int('id');
if ($id > 0) {
    $tpl_data['id'] = $id;
    $tpl_data['msg'] = m('msg')->findOne(We7::uniacid(['id' => $id]));
}

Response::showTemplate('web/agent/msg_edit', $tpl_data);