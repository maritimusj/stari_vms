<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Keeper;

defined('IN_IA') or exit('Access Denied');

if (!App::isPromoterEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

$type = Request::str('type');
$val = intval(round(Request::float('val', 0, 2) * 100));

if ($type == 'percent') {
    $val = max(0, min(10000, $val));
} elseif ($type == 'fixed') {
    $val = max(0, $val);
} else {
    JSON::fail('请求数据不正确！');
}

$keeper->updateSettings('promoter.commission', [
    $type => $val,
]);

JSON::success([
    'msg' => '保存成功！',
]);
