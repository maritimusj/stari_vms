<?php
namespace zovye;

use RuntimeException;

$op = request::op('default');

if ($op == 'list') {

    $device = Device::get(request::int('device'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $query = Package::query(['device_id' => $device->getId()]);
    $query->orderBy('id ASC');

    $result = [];
    foreach($query->findAll() as $entry) {
        $result[] = $entry->format(true);
    }

    JSON::success($result);

} elseif ($op == 'create') {
    $device = Device::get(request::int('deviceId'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $result = Util::transactionDo(function() use ($device) {
        $title = request::trim('title');
        $price = request::float('price', 0, 2);

        $goods_list = request::array('list');

        $package = Package::create([
            'device_id' => $device->getId(),
            'title' => $title,
            'price' => $price * 100,
        ]);

        if (empty($package)) {
            throw new RuntimeException('创建套餐失败！');
        }

        foreach($goods_list as $entry) {
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

} elseif ($op == 'detail') {

    $id = request::int('id');
    $package = Package::get($id);
    if (empty($package)) {
        JSON::fail('找不到这个套餐！');
    }

    $result = $package->format(true);
    JSON::success($result);

} elseif ($op == 'remove') {

    $result = Util::transactionDo(function () {
        $id = request::int('id');
        $package = Package::get($id);
        if (empty($package)) {
            throw new RuntimeException('找不到这个套餐！');
        }

        if (!$package->destroy()) {
            throw new RuntimeException('失败！');
        }

        return ['id' => $id, 'msg' => '成功！'];
    });

    JSON::result($result);
}