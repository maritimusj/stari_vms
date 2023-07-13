<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$result = Order::getCommissionDetail($id);
if (is_error($result)) {
    JSON::fail($result);
}

Response::templateJSON('web/order/detail','详情', [ 'list' => $result ]);