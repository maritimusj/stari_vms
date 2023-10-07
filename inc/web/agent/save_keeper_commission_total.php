<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Keeper;

defined('IN_IA') or exit('Access Denied');

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

if (Request::is_numeric('val')) {
    $keeper->setCommissionTotal(Request::int('val'));
} else {
    $keeper->setCommissionTotal(-1);
}

if ($keeper->save()) {
    JSON::success('保存成功！');
}

JSON::success('保存失败！');