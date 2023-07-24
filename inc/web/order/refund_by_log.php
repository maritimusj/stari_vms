<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$log = Pay::getPayLogById($id);
if (empty($log)) {
    JSON::fail('找不到这个支付记录！');
}

var_dump($log->getData());