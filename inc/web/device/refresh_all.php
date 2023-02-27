<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (Advertising::notifyAll(['all' => 1])) {
    JSON::success('已通知有设备更新！');
}

JSON::fail('通知失败！');