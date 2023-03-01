<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\msgModelObj;

$id = Request::int('id');
/** @var msgModelObj $msg */
$msg = m('agent_msg')->findOne(We7::uniacid(['id' => $id]));
if ($msg) {
    JSON::success(['title' => $msg->getTitle(), 'content' => html_entity_decode($msg->getContent())]);
}

JSON::fail('出错了，无法读取消息内容！');