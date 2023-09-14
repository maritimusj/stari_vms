<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\business\GDCVMachine;
use zovye\domain\Order;

defined('IN_IA') or exit('Access Denied');

$order_id = Request::int('id');

$order = Order::get($order_id);
if (empty($order)) {
    JSON::fail('对不起，找不到这个订单！');
}

if (GDCVMachine::scheduleUploadOrderLogJob($order)) {

    $order->setExtraData('CV.upload', ['uploading' => time()]);
    $order->save();
    
    JSON::success('已加入上传队列！');
}

JSON::fail('无法加入上传队列！');