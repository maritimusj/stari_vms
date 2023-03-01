<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;

$device_id = Request::int('deviceId');
if ($device_id) {
    $device = Device::get($device_id);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }
    if ($device->isBlueToothDevice()) {
        JSON::fail('暂时不支持蓝牙设备创建套餐！');
    }
}

$result = Util::transactionDo(function () use ($device_id) {
    $title = Request::trim('title');
    $price = Request::float('price', 0, 2);

    $goods_list = Request::array('list');

    $package = Package::create([
        'device_id' => $device_id,
        'title' => $title,
        'price' => $price * 100,
    ]);

    if (empty($package)) {
        throw new RuntimeException('创建套餐失败！');
    }

    foreach ($goods_list as $entry) {
        $goods = Goods::get($entry['id']);
        if (empty($goods)) {
            throw new RuntimeException('找不到这个商品！');
        }
        $package_goods = PackageGoods::create([
            'package_id' => $package->getId(),
            'goods_id' => $goods->getId(),
            'num' => intval($entry['num']),
            'price' => floatval($entry['price']) * 100,
        ]);
        if (empty($package_goods)) {
            throw new RuntimeException('创建套餐商品失败！');
        }
    }

    return [
        'id' => $package->getId(),
        'msg' => '创建成功！',
    ];
});

JSON::result($result);