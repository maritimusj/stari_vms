<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;
use zovye\domain\Package;
use zovye\util\DBUtil;

$result = DBUtil::transactionDo(function () {
    $id = Request::int('id');
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