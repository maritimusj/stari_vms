<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$op = request::op();

$tpl_data = [
    'op' =>$op,
];

$id = request::int('id');
if ($id) {
    $tpl_data['id'] = $id;
    $tpl_data['msg'] = m('msg')->findOne(We7::uniacid(['id' => $id]));
}

app()->showTemplate('web/agent/msg_edit', $tpl_data);