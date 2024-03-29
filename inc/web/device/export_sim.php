<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\domain\Device;
use zovye\model\deviceModelObj;
use zovye\util\Helper;
use zovye\util\Util;

$step = Request::str('step');
if (empty($step)) {
    $total = Device::query()->count();

    Response::templateJSON('web/common/export',
        '导出SIM卡信息',
        [
        'api_url' => Util::url('device', ['op' => 'export_sim']),
        'total' => $total,
        'serial' => (new DateTime())->format('YmdHis'),
    ]);
}

$serial = Request::str('serial');
if (empty($serial)) {
    JSON::fail("缺少serial");
}

$filename = "$serial.csv";
$dirname = "export/sim/";
$full_filename = Helper::getAttachmentFileName($dirname, $filename);

if ($step == 'load') {
    $last_id = Request::int('last');

    $result = [];

    $query = Device::query(['id >' => $last_id])->limit(10)->orderBy('id ASC');

    $n = 0;
    /** @var deviceModelObj $device */
    foreach ($query->findAll() as $device) {
        $last_id = $device->getId();

        $data = $device->getSIM();
        if (is_error($data)) {
            continue;
        }

        $result[] = [
            "'".$device->getImei(),
            "'".$data['iccid'],
            $data['carrier'],
            $data['status'],
            $data['data_plan'],
            $data['data_usage'],
            $data['active_date'],
            $data['expiry_date'],
        ];
        $n++;
    }

    Util::exportCSVToFile($full_filename, [
        '\'设备IMEI',
        '\'ICCID',
        '运营商',
        '状态',
        '套餐',
        '用量',
        '激活日期',
        '过期日期',
    ], $result);

    JSON::success([
        'num' => 10,
        'success' => $n,
        'last' => $last_id,
    ]);

} elseif ($step == 'download') {
    JSON::success([
        'url' => Util::toMedia("$dirname$filename"),
    ]);
}
