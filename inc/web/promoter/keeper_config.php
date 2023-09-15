<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Keeper;
use zovye\util\Helper;

defined('IN_IA') or exit('Access Denied');

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

$config = $keeper->settings('notice', []);

Response::templateJSON(
    'web/keeper/config',
    '配置',
    [
        'id' => $keeper->getId(),
        'config' => Helper::getWxPushMessageConfig($config),
    ]
);