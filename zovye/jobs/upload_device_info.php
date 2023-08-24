<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\upload_device_info;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\Device;
use zovye\HttpUtil;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\Request;
use function zovye\isEmptyArray;
use function zovye\settings;

$last_id = Request::int('lastId');
$log = [
    'lastId' => $last_id,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!');
}

$config = settings('device.upload', []);
$url = strval($config['url']);

if (empty($url)) {
    Log::fatal('upload_device_info', [
        'error' => '没有配置第三方API url',
    ]);
}

$query = Device::query();

if ($last_id > 0) {
    $query->where(['id >' => $last_id]);
}

$query->orderBy('id ASC');
$query->limit(DEFAULT_PAGE_SIZE);

$total = $query->count();

if ($total > 0) {
    /** @var deviceModelObj $device */
    $device = null;
    foreach ($query->findAll() as $device) {
        $extra = $device->get('extra', []);

        $log = [
            'name' => $device->getName(),
            'imei' => $device->getImei(),
            'iccid' => $device->getICCID() ?? '',
            'app_id' => $device->getAppId() ?? '',
            'model' => $device->getDeviceModel(),
            'location' => isEmptyArray(
                $extra['location']['tencent']
            ) ? $extra['location'] : $extra['location']['tencent'],
            'createtime' => $device->getCreatetime(),
        ];

        if (isEmptyArray($log['location']['area'])) {
            $agent = $device->getAgent();
            if ($agent) {
                $agent_data = $agent->getAgentData();
                if (!isEmptyArray($agent_data['area'])) {
                    $log['location']['area'] = array_values($agent_data['area']);
                }
            }
        } else {
            $log['location']['area'] = array_values($log['location']['area']);
        }

        if (isEmptyArray($log['location'])) {
            $log['location'] = null;
        }

        $log['sign'] = sign($log, strval($config['secret']));

        $res = HttpUtil::post($url, $log, true, 3, [
            CURLOPT_HTTPHEADER => ["APPKEY: {$config['key']}"],
        ]);

        Log::info('upload_device_info', [
            'data' => $log,
            'response' => $res,
        ]);
    }
    if ($device) {
        Job::uploadDeviceInfo($device->getId());
    }
}

function sign(array $data, string $secret): string
{
    ksort($data);
    $str = http_build_query($data);

    return md5($str.$secret);
}