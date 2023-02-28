<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;
use zovye\model\deviceModelObj;

$step = Request::str('step');
if (empty($step)) {
    $total = Device::query()->count();

    $content = app()->fetchTemplate('web/common/export', [
        'api_url' => Util::url('device', ['op' => 'export_sim']),
        'total' => $total,
        'serial' => (new DateTime())->format('YmdHis'),
    ]);

    JSON::success([
        'title' => "导出SIM卡信息",
        'content' => $content,
    ]);
}

$serial = Request::str('serial');
if (empty($serial)) {
    JSON::fail("缺少serial");
}

$filename = "$serial.csv";
$dirname = "export/sim/";
$full_filename = Util::getAttachmentFileName($dirname, $filename);

if ($step == 'load') {
    $last_id = Request::int('last');

    $result = [];

    $query = Device::query(['id >' => $last_id])->limit(10)->orderBy('id asc');

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

    Util::exportExcelFile($full_filename, [
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
