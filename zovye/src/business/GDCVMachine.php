<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\business;

use zovye\Config;
use zovye\CtrlServ;
use zovye\domain\Device;
use zovye\domain\DeviceLogs;
use zovye\Log;
use zovye\model\device_logsModelObj;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\util\HttpUtil;
use zovye\We7;
use function zovye\err;
use function zovye\isEmptyArray;
use function zovye\m;

class GDCVMachine
{
    private $config;

    public function __construct()
    {
        $this->config = Config::GDCVMachine('config', []);
    }

    protected function sign($ts): string
    {
        return md5("{$this->config['appId']}{$this->config['token']}$ts");
    }

    protected function post($path, $data = []): array
    {
        if (isEmptyArray($this->config)) {
            return err('配置不正确，请检查配置后再试！');
        }

        $ts = time() * 1000;

        $url = rtrim($this->config['url'], '/\\');
        $url .= $path;
        $url .= '?';
        $url .= http_build_query([
            'appId' => $this->config['appId'],
            'timeStamp' => $ts,
            'sign' => $this->sign($ts),
        ]);

        $response = HttpUtil::post($url, $data);

        Log::debug('GDCVMachine', [
            'url' => $url,
            'config' => $this->config,
            'data' => $data,
            'response' => $response,
        ]);

        return $response;
    }

    function formatDevice(deviceModelObj $device): array
    {
        $location = $device->getLocation();
        $data = [
            'machineCode' => $device->getImei(),
            'agentCode' => strval($this->config['agent']),
            'location' => isset($location['lat']) && isset($location['lng']) ? "{$location['lat']},{$location['lng']}" : '',
            'connectionStatus' => $device->isMcbOnline() ? 1 : 2,   // 在线状态？1,正常，2,离线
            'machineStatus' => $device->isMaintenance() ? 2 : 1,           // 设备状态？1,正常， 2,故障
            'stockStatus' => $device->getS2() ? 2 : 1,              // 是否缺货？1,正常，2，缺货
            'channels' => [],
        ];

        $payload = $device->getPayload(true);
        if (is_array($payload['cargo_lanes'])) {
            foreach ($payload['cargo_lanes'] as $index => $lane) {
                $code = $lane['CVMachine.code'];
                if ($code) {
                    $data['channels'][] = [
                        'index' => $index + 1,
                        'productCode' => $code,
                        'status' => 1,
                        'quantity' => $lane['num'],
                    ];
                }
            }
        }

        return $data;
    }

    public static function scheduleUploadDeviceJob(deviceModelObj $device)
    {
        m('cv_upload_device')->create(We7::uniacid([
            'device_id' => $device->getId(),
        ]));

        $last_ts = Config::GDCVMachine('last.device_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));

        $result = CtrlServ::scheduleDelayJob('upload_cv_info', ['w' => 'device'], $delay);

        if (empty($result)) {
            Log::error('CV_device_log', [
                'device' => $device->profile(),
                'error' => '无法启动任务！',
            ]);
        }

        return $result;
    }

    public static function scheduleUploadDeviceJobForDeviceType(int $type_id)
    {
        $query = Device::query(['device_type' => $type_id]);
        foreach ($query->findAll() as $device) {
            m('cv_upload_device')->create(We7::uniacid([
                'device_id' => $device->getId(),
            ]));
        }

        $last_ts = Config::GDCVMachine('last.device_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));

        $result = CtrlServ::scheduleDelayJob('upload_cv_info', ['w' => 'device'], $delay);

        if (empty($result)) {
            Log::error('CV_device_log', [
                'type_id' => $type_id,
                'error' => '无法启动任务！',
            ]);
        }

        return $result;
    }

    public static function scheduleUploadOrderLogJob(orderModelObj $order)
    {
        m('cv_upload_order')->create(We7::uniacid([
            'order_id' => $order->getId(),
        ]));

        $last_ts = Config::GDCVMachine('last.order_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));

        $result = CtrlServ::scheduleDelayJob('upload_cv_info', ['w' => 'order'], $delay);

        if (empty($result)) {
            Log::error('CV_order_log', [
                'order' => $order->profile(),
                'error' => '无法启动任务！',
            ]);
        }

        return $result;       
    }

    public function uploadDevicesInfo(array $list = []): array
    {
        $data = [];

        /** @var deviceModelObj $device */
        foreach ($list as $device) {
            if (!$data[$device->getId()]) {
                $data[$device->getId()] = $this->formatDevice($device);
            }
        }

        if ($data) {
            $response = $this->post('/cgi-bin/machineinfo', array_values($data));

            Log::debug('CV_device_log', [
                'request' => $data,
                'response' => $response,
            ]);

           return $response;
        }

        return [];
    }

    public function uploadDeviceInfo(deviceModelObj $device)
    {
        $this->uploadDevicesInfo([$device]);
    }

    function formatOrder(orderModelObj $order): array
    {
        $goods = $order->getGoodsData();
        $profile = $order->getExtraData('CV.profile', []);

        return [
            'machineCode' => $order->getExtraData('device.imei', ''),
            'agentCode' => strval($this->config['agent']),
            'channelCode' => $order->getDeviceChannelId() + 1,
            'productCode' => strval($goods['CVMachine.code']),
            'billNumber' => $order->getOrderNO(),
            'quantity' => $order->getNum(),
            'time' => date('Y-m-d H:i:s', $order->getCreatetime()),
            'type' => $order->getExtraData('CV.type', 2), // 领取方式，1，身份证，2，二维码
            'identity' => $profile['num'] ?? '',
            'name' => $profile['name'] ?? '',
            'gender' => $profile['gender'] ?? '',
        ];
    }

    public function formatOrderLog(orderModelObj $order): array
    {
        $profile = $order->getExtraData('CV.profile', []);

        $condition = We7::uniacid([
            'createtime >=' => $order->getCreatetime(),
            'createtime <' => $order->getCreatetime() + 3600,
            'data REGEXP' => "s:5:\"order\";i:{$order->getId()};",
        ]);

        $device = $order->getDevice();
        if ($device) {
            $condition['title'] = $device->getImei();
        }

        $query = DeviceLogs::query($condition);

        $list = [];
        /** @var device_logsModelObj $entry */
        foreach ($query->findAll() as $index => $entry) {
            $goods = $entry->getData('goods', []);
            $list["{$order->getId()}:$index"] = [
                'machineCode' => $device->getImei(),
                'agentCode' => strval($this->config['agent']),
                'channelCode' => $entry->getData('ch') + 1,
                'productCode' => strval($goods['CVMachine.code']),
                'billNumber' => $order->getOrderNO(),
                'quantity' => $order->getNum(),
                'time' => date('Y-m-d H:i:s', $order->getCreatetime()),
                'type' => $order->getExtraData('CV.type', 2), // 领取方式，1，身份证，2，二维码
                'identity' => $profile['num'] ?? '',
                'name' => $profile['name'] ?? '',
                'gender' => $profile['gender'] ?? '',
            ];
        }

        return $list;
    }

    public function uploadOrdersInfo(array $list = []): array
    {
        $data = [];

        /** @var orderModelObj $order */
        foreach ($list as $order) {
            if (!$data[$order->getId()]) {
                if ($order->getNum() == 1) {
                    $data[$order->getId()] = $this->formatOrder($order);
                } else {
                    $logs = $this->formatOrderLog($order);
                    if (count($logs) > 0) {
                        $data[$order->getId()] = array_shift($logs);
                        $data = array_merge($data, $logs);
                    } else {
                        $data[$order->getId()] = $this->formatOrder($order);
                    }
                }
            }
        }

        if ($data) {
            $response = $this->post('/cgi-bin/machleadrecord', array_values($data));

            Log::debug('CV_order_log', [
                'request' => $data,
                'response' => $response,
            ]);

            return $response;
        }

        return [];
    }

    public function uploadOrderInfo(orderModelObj $order)
    {
        $this->uploadOrdersInfo([$order]);
    }
}