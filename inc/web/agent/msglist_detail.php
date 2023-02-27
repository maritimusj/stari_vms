<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;


use zovye\model\msgModelObj;

$id = request::int('id');
/** @var msgModelObj $msg */
$msg = m('agent_msg')->findOne(We7::uniacid(['id' => $id]));
if ($msg) {
    JSON::success(['title' => $msg->getTitle(), 'content' => html_entity_decode($msg->getContent())]);
}

JSON::fail('出错了，无法读取消息内容！');