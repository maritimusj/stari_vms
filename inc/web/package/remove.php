<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use RuntimeException;

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