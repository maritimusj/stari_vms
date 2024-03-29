<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;
use zovye\domain\Device;
use zovye\util\CacheUtil;
use zovye\util\Util;

$id = Request::int('id');

$device = Device::get($id);
if (empty($device)) {
    JSON::fail();
}

$result = CacheUtil::cachedCall(60, function () use ($device) {

    if (Util::isSysLoadAverageOk()) {
        return $device->getPullStats();
    }

    throw new RuntimeException('系统繁忙！');

}, $device->getId());

JSON::result($result);