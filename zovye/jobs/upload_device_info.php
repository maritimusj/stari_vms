<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\upload_device_info;

use zovye\CtrlServ;
use zovye\Device;
use zovye\Job;
use zovye\Log;
use zovye\request;
use zovye\Util;

use function zovye\settings;

$op = request::op('default');
$lastId = request::int('lastId');
$data = [
    'lastId' => $lastId,
];

if ($op == 'upload_device_info' && CtrlServ::checkJobSign($data)) {

    $config = settings('device.upload', []);
    $url = strval($config['url']);

    if (empty($url)) {
        Log::fatal('upload_device_info', [
            'error' => '没有配置第三方API url',
        ]);
    }

    $query = Device::query();

    if ($lastId > 0) {
        $query->where(['id >' => $lastId]);
    }

    $query->orderBy('id ASC');
    $query->limit(DEFAULT_PAGE_SIZE);

    $total = $query->count();

    if ($total > 0) {
        /** @var deviceModelObj $device */
        $device = null;
        foreach($query->findAll() as $device) {
            $data = [
                'name' => $device->getName(),
                'imei' => $device->getImei(),
                'iccid' => $device->getIccid(),
                'app_id' => $device->getAppId(),
            ];

            $data['sign'] = sign($data, strval($config['secret']));
            $res = Util::post($url, $data);
            Log::info('upload_device_info', [
                'data' => $data,
                'response' => $res,
            ]);
        }
        if ($device) {
            Job::uploadDevieInfo($device->getId());
        }
    }
}

function sign(array $data, string $secret): string
{
    ksort($data);
    $str = http_build_query($data);
    return md5($str . $secret);
}