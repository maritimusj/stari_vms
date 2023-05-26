<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Traversable;
use zovye\model\device_logsModelObj;
use zovye\model\deviceModelObj;

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

    public function uploadDeviceInfo(Traversable $deviceIterator)
    {
        $data = [];

        /** @var deviceModelObj $device */
        foreach ($deviceIterator as $device) {
            $location = $device->getLocation();
            $v = [
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
                    $v['channels'][] = [
                        'index' => $index + 1,
                        'productCode' => 313,//strval($lane['CVMachine.code']),
                        'status' => 1,
                        'quantity' => $lane['num'],
                    ];
                }
            }

            $data[] = $v;
        }

        $response = $this->post('/cgi-bin/machineinfo', $data);

        if (empty($response)) {
            return err('返回数据为空！');
        }

        if ($response['code'] === 0) {
            return true;
        }

        return err($response['message']);
    }

    public function uploadOrderInfo(Traversable $orderIterator)
    {
        $data = [];

        /** @var device_logsModelObj $entry */
        foreach ($orderIterator as $entry) {
            $goods = $entry->getData('goods');
            $user = $entry->getData('user');
            $order = Order::get($entry->getData('order'));
            $params = $entry->getData('params');
            $data[] = [
                'machineCode' => $entry->getTitle(),
                'agentCode' => strval($this->config['agent']),
                'channelCode' => $goods['cargo_lane'] + 1,
                'productCode' => strval($goods['CVMachine.code']),
                'billNumber' => $order ? $order->getOrderNO() : '',
                'quantity' => intval($params['num']),
                'time' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'type' => 2, // 领取方式，1，身份证，2，二维码
                'identity' => strval($user['identity']),
                'name' => strval($user['name']),
                'gender' => $user['sex'] == 1 ? '男' : '女',
            ];
        }

        $response = $this->post('/cgi-bin/machleadrecord', $data);

        if (empty($response)) {
            return err('返回数据为空！');
        }

        if ($response['code'] === 0) {
            return true;
        }

        return err($response['message']);
    }
}