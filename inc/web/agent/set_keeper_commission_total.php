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

Response::templateJSON(
    'web/keeper/commission_total',
    "{$keeper->getName()}的剩余有效补货数量",
    [
        'id' => $keeper->getId(),
        'total' => $keeper->getCommissionLimitTotal(),
    ]
);