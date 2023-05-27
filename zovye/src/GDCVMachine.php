<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;

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

        $response = Util::post($url, $data);

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
            'machineStatus' => $device->isDown() ? 2 : 1,           // 设备状态？1,正常， 2,故障
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
        $last_ts = Config::GDCVMachine('last.device_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));

        return CtrlServ::scheduleDelayJob('upload_gv_info', ['w' => 'device', 'id' => $device->getId()], $delay);
    }

    public static function scheduleUploadDeviceJobForDeviceType(int $type_id)
    {
        $last_ts = Config::GDCVMachine('last.device_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));

        return CtrlServ::scheduleDelayJob('upload_gv_info', ['w' => 'types', 'id' => $type_id], $delay);
    }

    public static function scheduleUploadOrderLogJob(orderModelObj $order)
    {
        $last_ts = Config::GDCVMachine('last.order_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));

        return CtrlServ::scheduleDelayJob('upload_gv_info', ['w' => 'order', 'id' => $order->getId()], $delay);
    }

    public function uploadDevicesInfo(array $list = [])
    {
        $data = [];

        foreach ($list as $device) {
            $data[] = $this->formatDevice($device);
        }

        $response = $this->post('/cgi-bin/machineinfo', $data);

        Log::debug('CV_device_log', [
            'request' => $data,
            'response' => $response,
        ]);

        if (empty($response)) {
            return err('返回数据为空！');
        }

        if ($response['code'] === 0) {
            return true;
        }

        Log::error('CV_device_log', [
            'request' => $data,
            'response' => $response,
        ]);

        return err($response['message']);
    }

    public function uploadDeviceInfo(deviceModelObj $device)
    {
        return $this->uploadDevicesInfo([$device]);
    }

    function formatOrder(orderModelObj $order): array
    {
        $device = $order->getDevice();
        $goods = $order->getGoodsData();
        $profile = $order->getExtraData('CV.profile', []);

        return [
            'machineCode' => $device->getImei(),
            'agentCode' => strval($this->config['agent']),
            'channelCode' => $order->getDeviceChannelId() + 1,
            'productCode' => strval($goods['CVMachine.code']),
            'billNumber' => $order->getOrderNO(),
            'quantity' => $order->getNum(),
            'time' => date('Y-m-d H:i:s', $order->getCreatetime()),
            'type' => $order->getExtraData('CV.type', 2), // 领取方式，1，身份证，2，二维码
            'identity' => $profile['identity'],
            'name' => $profile['name'],
            'gender' => $profile['gender'],
        ];
    }

    public function uploadOrdersInfo(array $list = [])
    {
        $data = [];

        foreach ($list as $order) {
            $data[] = $this->formatOrder($order);
        }

        $response = $this->post('/cgi-bin/machleadrecord', $data);

        Log::debug('CV_order_log', [
            'request' => $data,
            'response' => $response,
        ]);

        if (empty($response)) {
            return err('返回数据为空！');
        }

        $result = array_merge($response, [ 'ts' => time() ]);

        /** @var orderModelObj $order */
        foreach ($list as $order) {
            $order->setExtraData('CV.upload', $result);
            $order->save();
        }

        if ($response['code'] === 0) {
            return true;
        }

        Log::error('CV_order_log', [
            'request' => $data,
            'response' => $response,
        ]);

        return err($response['message']);
    }

    public function uploadOrderInfo(orderModelObj $order)
    {
        return $this->uploadOrdersInfo([$order]);
    }
}