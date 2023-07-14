<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\upload_device_info;

use zovye\CtrlServ;
use zovye\Device;
use zovye\HttpUtil;
use zovye\Job;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\Request;

use function zovye\isEmptyArray;
use function zovye\settings;

$op = Request::op('default');
$lastId = Request::int('lastId');
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
        foreach ($query->findAll() as $device) {
            $extra = $device->get('extra', []);

            $data = [
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

            if (isEmptyArray($data['location']['area'])) {
                $agent = $device->getAgent();
                if ($agent) {
                    $agent_data = $agent->getAgentData();
                    if (!isEmptyArray($agent_data['area'])) {
                        $data['location']['area'] = array_values($agent_data['area']);
                    }
                }
            } else {
                $data['location']['area'] = array_values($data['location']['area']);
            }

            if (isEmptyArray($data['location'])) {
                $data['location'] = null;
            }

            $data['sign'] = sign($data, strval($config['secret']));

            $res = HttpUtil::post($url, $data, true, 3, [
                CURLOPT_HTTPHEADER => ["APPKEY: {$config['key']}"],
            ]);

            Log::info('upload_device_info', [
                'data' => $data,
                'response' => $res,
            ]);
        }
        if ($device) {
            Job::uploadDeviceInfo($device->getId());
        }
    }
}

function sign(array $data, string $secret): string
{
    ksort($data);
    $str = http_build_query($data);

    return md5($str.$secret);
}