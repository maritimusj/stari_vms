<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
if (Request::has('num')) {
    $num = Request::int('num');

    $res = Order::refund($id, $num, [
        'admin' => _W('username'),
        'ip' => CLIENT_IP,
        'message' => '管理员退款',
    ]);
} elseif (Request::has('price')) {
    $price = Request::int('price');

    $res = Order::refund2($id, $price, [
        'admin' => _W('username'),
        'ip' => CLIENT_IP,
        'message' => '管理员退款',
    ]);
} else {
    JSON::fail('参数不正确！');
}

if (is_error($res)) {
    JSON::fail($res);
}

JSON::success('退款成功！');