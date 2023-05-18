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
    private $config = null;

    public function __construct()
    {
        $this->config = Config::GDCVMachine('config', []);
    }

    protected function sign($ts): string
    {
        return md5($this->config['appId'].$this->config['token'].$ts);
    }

    protected function post($data = []): array
    {
        if (isEmptyArray($this->config)) {
            return err('配置不正确，请检查配置后再试！');
        }

        $ts = time();

        $url = $this->config['url'].http_build_query([
                'appId' => $this->config['appId'],
                'timestamp' => $ts,
                'sign' => $this->sign($ts),
            ]);

        return Util::post($url, $data);
    }

    public function uploadDeviceInfo(Traversable $deviceIterator)
    {
        $data = [];

        /** @var deviceModelObj $device */
        foreach ($deviceIterator as $device) {
            $location = $device->getLocation();var_dump($location);
            $v = [
                'machineCode' => $device->getImei(),
                'agentCode' => strval($this->config['agent']),
                'location' => "{$location['lat']},{$location['lng']}",
                'connectionStatus' => $device->isMcbOnline() ? 1 : 2,   //在线状态？1,正常，2,离线
                'machineStatus' => $device->isDown() ? 2 : 1,           // 设备状态？1,正常， 2,故障
                'stockStatus' => $device->getS2() ? 2 : 1,              //是否缺货？1,正常，2，缺货
                'channels' => [],
            ];

            $payload = $device->getPayload(true);
            if (is_array($payload['cargo_lanes'])) {
                foreach ($payload['cargo_lanes'] as $index => $lane) {
                    $v['channels'][] = [
                        'index' => $index + 1,
                        'productCode' => strval($lane['CVMachine.code']),
                        'status' => 1,
                        'quantity' => $lane['num'],
                    ];
                }
            }

            $data[] = $v;
        }

        $response = $this->post($data);

        Log::debug('GDCVMachine', [
            'func' => 'upload device info',
            'data' => $data,
            'response' => $response,
        ]);

    }

    public function uploadOrder(Traversable $orderIterator)
    {
        $data = [];

        /** @var device_logsModelObj $entry */
        foreach ($orderIterator as $entry) {
            print_r($entry->getData());
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
                'quantity' => $params['num'],
                'time' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'type' => 2, //领取方式，1，身份证，2，二维码
                'identity' => $user['identity'],
                'name' => strval($user['name']),
                'gender' => $user['sex'] == 1 ? '男' : '女',
            ];
        }

        $response = $this->post($data);

        Log::debug('GDCVMachine', [
            'func' => 'upload order',
            'data' => $data,
            'response' => $response,
        ]);
    }
}