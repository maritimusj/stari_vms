<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Agent;
use zovye\domain\Keeper;
use zovye\model\keeperModelObj;
use zovye\util\Helper;
use zovye\util\Util;

$id = Request::int('id');

$agent = Agent::get($id);
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$query = Keeper::query(['agent_id' => $agent->getId()]);

$result = [];

$getCommissionLimitFN = App::isKeeperCommissionLimitEnabled() ? function () {
    return '未启用';
} : function ($keeper) {
    $total = $keeper->getCommissionLimitTotal();

    return $total == -1 ? '未设置' : $total;
};
/** @var keeperModelObj $keeper */
foreach ($query->findAll() as $index => $keeper) {
    $user = $keeper->getUser();
    if (!$user) {
        continue;
    }
    $data = [
        $index + 1,
        $user->getNickname(),
        $keeper->getName(),
        $keeper->getMobile(),
        intval($keeper->deviceQuery()->count()),
        number_format($user->getBalance()->total() / 100.00, 2, '.', ''),
        $getCommissionLimitFN($keeper),
        date('Y-m-d H:i:s', $keeper->getCreatetime()),
    ];
    $result[] = $data;
}

$headers = [
    '#',
    '用户',
    '姓名',
    '手机号码',
    '设备数量',
    '余额',
    App::isKeeperCommissionLimitEnabled() ? '剩余有效补货数量' : '',
    '创建时间',
];

$filename = date("YmdHis").'.csv';
$dirname = "export/keeper/";

$full_filename = Helper::getAttachmentFileName($dirname, $filename);

Util::exportCSVToFile($full_filename, $headers, $result);

JSON::success([
    'filename' => Util::toMedia("$dirname$filename"),
]);