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

$content = app()->fetchTemplate(
    'web/order/detail',
    [
        'list' => $result,
    ]
);

JSON::success(['title' => '详情', 'content' => $content]);