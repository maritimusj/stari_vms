<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use RuntimeException;
use zovye\model\packageModelObj;

$id = request::int('id');

$device = null;
$result = Util::transactionDo(function () use ($id, &$device) {
    $data = [
        'agent_id' => 0,
        'name' => request::trim('name'),
        'imei' => request::trim('IMEI'),
        'group_id' => request::int('group'),
        'capacity' => max(0, request::int('capacity')),
        'remain' => max(0, request::int('remain')),
    ];

    $tags = request::trim('tags');
    $extra = [
        'pushAccountMsg' => request::trim('pushAccountMsg'),
        'isDown' => request::bool('isDown') ? Device::STATUS_MAINTENANCE : Device::STATUS_NORMAL,
        'activeQrcode' => request::bool('activeQrcode') ? 1 : 0,
        'address' => request::trim('address'),
        'grantloc' => [
            'lng' => floatval(request('location')['lng']),
            'lat' => floatval(request('location')['lat']),
        ],
        'txt' => [request::trim('first_txt'), request::trim('second_txt'), request::trim('third_txt')],
        'theme' => request::str('theme'),
    ];

    if (App::isDeviceWithDoorEnabled()) {
        $extra['door'] = [
            'num' => request::int('doorNum', 1),
        ];
    }

    if (App::isMustFollowAccountEnabled()) {
        $extra['mfa'] = [
            'enable' => request::int('mustFollow'),
        ];
    }

    if (App::isMoscaleEnabled()) {
        $extra['moscale'] = [
            'key' => request::trim('moscaleMachineKey'),
            'label' => array_map(function ($e) {
                return intval($e);
            }, explode(',', request::trim('moscaleLabel'))),
            'region' => [
                'province' => request::int('province_code'),
                'city' => request::int('city_code'),
                'area' => request::int('area_code'),
            ],
        ];
    }

    if (App::isZeroBonusEnabled()) {
        setArray($extra, 'custom.bonus.zero.v', min(100, request::float('zeroBonus', -1, 2)));
    }

    if (App::isFlashEggEnabled()) {
        setArray($extra, 'ad.device.uid', request::trim('adDeviceUID'));
    }

    if (empty($data['name']) || empty($data['imei'])) {
        throw new RuntimeException('设备名称或IMEI不能为空！');
    }

    $type_id = request::int('deviceType');
    if ($type_id) {
        $device_type = DeviceTypes::get($type_id);
        if (empty($device_type)) {
            throw new RuntimeException('设备类型不正确！');
        }
    }

    $data['device_type'] = $type_id;

    if (App::isBluetoothDeviceSupported() && request::str('device_model') == Device::BLUETOOTH_DEVICE) {
        $extra['bluetooth'] = [
            'protocol' => request::str('blueToothProtocol'),
            'uid' => request::trim('BUID'),
            'mac' => request::trim('MAC'),
            'motor' => request::int('Motor'),
            'screen' => request::int('blueToothScreen') ? 1 : 0,
            'power' => request::int('blueToothPowerSupply') ? 1 : 0,
            'disinfectant' => request::int('blueToothDisinfectant') ? 1 : 0,
        ];
    }

    if (App::isFuelingDeviceEnabled() && request::str('device_model') == Device::FUELING_DEVICE) {
        $extra['pulse'] = request::int('pulse');
        $extra['timeout'] = request::int('timeout');
        $extra['solo'] = request::bool('solo') ? 1 : 0;
        $extra['expiration'] = request::str('expiration');
    }

    $agent_id = request::int('agent_id');
    if ($agent_id) {
        $agent = Agent::get($agent_id);
        if (empty($agent)) {
            throw new RuntimeException('找不到这个代理商！');
        }

        $data['agent_id'] = $agent->getId();
    }

    $now = time();

    if ($id) {
        $device = Device::get($id);
        if (empty($device)) {
            throw new RuntimeException('设备不存在！');
        }

        if ($data['shadow_id']) {
            $device->setShadowId($data['shadow_id']);
        }

        if ($data['agent_id'] != $device->getAgentId()) {
            if ($device->getAgentId() > 0 && !Device::unbind($device)) {
                throw new RuntimeException('无法解除代理商与设备的绑定关系！');
            }
            $device->setAgentId($data['agent_id']);
        }

        if ($data['name'] != $device->getName()) {
            $device->setName($data['name']);
        }

        if (!$device->payloadLockAcquire(1)) {
            throw new RuntimeException('设备正忙，请稍后再试！');
        }

        if ($data['device_type'] != $device->getDeviceType()) {
            $res = $device->resetPayload(['*' => '@0'], '管理员改变型号', $now);
            if (is_error($res)) {
                throw new RuntimeException('保存库存失败！');
            }
            $device->setDeviceType($data['device_type']);
        }

        if ($data['group_id'] != $device->getGroupId()) {
            $device->setGroupId($data['group_id']);
        }

        if (request::isset('volume')) {
            $vol = max(0, min(100, request::int('volume')));
            if ($vol != $device->settings('extra.volume')) {
                $extra['volume'] = $vol;
            }
        }
    } else {
        $device = Device::create($data);
        if (empty($device)) {
            throw new RuntimeException('创建失败！');
        }

        $model = request::str('device_model');

        $device->setDeviceModel($model);

        if ($device->isNormalDevice() || $device->isChargingDevice() || $device->isFuelingDevice()) {
            $activeRes = Util::activeDevice($device->getImei());
        }

        //绑定套餐
        if (!$device->isBlueToothDevice()) {
            /** @var packageModelObj $entry */
            foreach (Package::query(['device_id' => 0])->findAll() as $entry) {
                $entry->setDeviceId($device->getId());
                $entry->save();
            }
        }

        //绑定appId
        $device->updateAppId();
    }

    if ($device->isChargingDevice()) {
        $device->setDeviceType(0);

        $device_type = DeviceTypes::from($device);
        if (empty($device_type)) {
            throw new RuntimeException('设备类型不正确！');
        }
        $cargo_lanes = [];
        for ($i = 0; $i < request::int('chargerNum'); $i++) {
            $cargo_lanes[] = [];
        }
        $device_type->setExtraData('cargo_lanes', $cargo_lanes);
        $device_type->save();

        $res = ChargingServ::setDeviceGroup($device);
        if (is_error($res)) {
            throw new RuntimeException('同步分组信息失败！');
        }
    } else {
        //处理自定义型号
        if (empty($type_id)) {
            $device->setDeviceType(0);

            $device_type = DeviceTypes::from($device);
            if (empty($device_type)) {
                throw new RuntimeException('设备类型不正确！');
            }

            $old = $device_type->getExtraData('cargo_lanes', []);

            $cargo_lanes = [];
            $capacities = request::array('capacities');
            $is_fueling =  $device->isFuelingDevice();

            foreach (request::array('goods') as $index => $goods_id) {
                $cargo_lanes[] = [
                    'goods' => intval($goods_id),
                    'capacity' => $is_fueling ? intval(round($capacities[$index] * 100)) : intval($capacities[$index]),
                ];
                if ($old[$index] && $old[$index]['goods'] != intval($goods_id)) {
                    $device->resetPayload([$index => '@0'], $is_fueling ? '管理员更改加注枪商品' : '管理员更改货道商品', $now);
                }
                unset($old[$index]);
            }

            foreach ($old as $index => $lane) {
                $device->resetPayload([$index => '@0'], $is_fueling ? '管理员删除加注枪' : '管理员删除货道', $now);
            }

            $device_type->setExtraData('cargo_lanes', $cargo_lanes);
            $device_type->save();
        }
    }

    if (empty($device_type)) {
        throw new RuntimeException('获取型号失败！');
    }

    $is_fueling =  $device->isFuelingDevice();

    //货道商品数量和价格
    $type_data = DeviceTypes::format($device_type);
    $cargo_lanes = [];
    foreach ($type_data['cargo_lanes'] as $index => $lane) {
        if ( $is_fueling) {
            $num = intval(request::float("lane{$index}_num", 0, 2) * 100);
        } else {
            $num = request::int("lane{$index}_num");
        }
        $cargo_lanes[$index] = [
            'num' => '@'.max(0, $num),
        ];
        if ($device_type->getDeviceId() == $device->getId()) {
            $cargo_lanes[$index]['price'] = request::float("price$index", 0, 2) * 100;
        }
    }

    $res = $device->resetPayload($cargo_lanes, '管理员编辑设备', $now);
    if (is_error($res)) {
        throw new RuntimeException('保存设备库存数据失败！');
    }

    $location = request::array('location');
    $extra['location']['baidu']['lat'] = $location['lat'];
    $extra['location']['baidu']['lng'] = $location['lng'];

    $saved_baidu_loc = $device->settings('extra.location.baidu', []);
    if (
        strval($saved_baidu_loc['lng']) != strval($location['lng'])
        || strval($saved_baidu_loc['lat']) != strval($location['lat'])
    ) {
        $address = Util::getLocation($location['lng'], $location['lat']);
        if ($address) {
            $extra['location']['baidu']['area'] = [
                $address['province'],
                $address['city'],
                $address['district'],
            ];
            $extra['location']['baidu']['address'] = $address['address'];
        } else {
            $extra['location']['area'] = [];
            $extra['location']['address'] = [];
        }
    } else {
        $extra['location']['baidu'] = $device->settings('extra.location.baidu');
    }

    $extra['location']['tencent'] = $device->settings('extra.location.tencent', []);
    $extra['goodsList'] = request::trim('goodsList');

    $extra['schedule'] = [
        'screen' => [
            'enabled' => request::bool('screenSchedule') ? 1 : 0,
            'on' => request::str('start'),
            'off' => request::str('end'),
        ],
    ];

    $original_extra = $device->get('extra', []);
    if ($original_extra['schedule']['screen'] !== $extra['schedule']['screen']) {
        $device->appNotify('config', [
            'schedule' => $extra['schedule']['screen'],
        ]);
    }

    //合并extra
    $extra = array_merge($original_extra, $extra);

    if (!$device->set('extra', $extra)) {
        throw new RuntimeException('保存扩展数据失败！');
    }

    $device->setTagsFromText($tags);
    $device->setDeviceModel(request('device_model'));
    if (!$device->save()) {
        throw new RuntimeException('保存数据失败！');
    }

    $msg = '保存成功';
    $error = false;

    if ($device->isFuelingDevice()) {
        if ($device->isMcbOnline()) {
            $res = Fueling::config($device);
            if (is_error($res)) {
                $msg .= '，发生错误：'.$res['message'];
                $error = true;
            }
        }
    } elseif ($device->isChargingDevice()) {
        //todo 暂无操作
    } else {
        if (App::isZJBaoEnabled()) {
            $device->updateSettings('zjbao.scene', request::trim('ZJBao_Scene'));
        }

        //更新公众号缓存
        $device->updateAccountData();

        $device->updateScreenAdvsData();

        $device->updateAppVolume();

        $device->updateAppRemain();
    }

    $res = $device->updateQrcode(true);

    if (is_error($res)) {
        $msg .= ', 发生错误：'.$res['message'];
        $error = true;
    }

    if (isset($activeRes) && is_error($activeRes)) {
        $msg .= '，发生错误：无法激活设备！';
        $error = true;
    }

    return ['error' => $error, 'message' => $msg];
});

if (is_error($result)) {
    Util::itoast($result['message'], $id ? We7::referer() : $this->createWebUrl('device'), 'error');
}

Util::itoast(
    $result['message'],
    $this->createWebUrl(
        'device',
        ['op' => 'edit', 'id' => $device ? $device->getId() : $id, 'from' => request::str('from')]
    ),
    $result['error'] ? 'warning' : 'success'
);