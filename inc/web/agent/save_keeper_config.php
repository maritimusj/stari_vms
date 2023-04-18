<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isPromoterEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

$config = [
    Request::str('type') => intval(Request::float('val', 0, 2) * 100),
];

$keeper->updateSettings('promoter.commission', $config);

JSON::success([
    'msg' => '保存成功！',
]);
