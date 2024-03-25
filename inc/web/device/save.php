<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use RuntimeException;
use zovye\business\ChargingServ;
use zovye\business\Fueling;
use zovye\business\GDCVMachine;
use zovye\business\TKPromoting;
use zovye\domain\Account;
use zovye\domain\Agent;
use zovye\domain\Device;
use zovye\domain\DeviceTypes;
use zovye\domain\GoodsExpireAlert;
use zovye\domain\Package;
use zovye\domain\PaymentConfig;
use zovye\model\packageModelObj;
use zovye\util\DBUtil;
use zovye\util\Helper;
use zovye\util\LocationUtil;
use zovye\util\Util;

$id = Request::int('id');
$device = null;

$result = DBUtil::transactionDo(function () use ($id, &$device) {
    $data = [
        'agent_id' => 0,
        'name' => Request::trim('name'),
        'imei' => Request::trim('IMEI'),
        'group_id' => Request::int('group'),
        'remain' => max(0, Request::int('remain')),
        's3' => Request::bool('isDown') ? Device::STATUS_MAINTENANCE : Device::STATUS_NORMAL,
    ];

    $extra = [
        'pushAccountMsg' => Request::trim('pushAccountMsg'),
        'activeQrcode' => Request::bool('activeQrcode') ? 1 : 0,
        'address' => Request::trim('address'),
        'grantloc' => [
            'lng' => Request::float('location.lng'),
            'lat' => Request::float('location.lat'),
        ],
        'theme' => Request::str('theme'),
    ];

    if (App::isDeviceWithDoorEnabled()) {
        $extra['door'] = [
            'num' => Request::int('doorNum', 1),
        ];
    }

    if (App::isMustFollowAccountEnabled()) {
        $extra['mfa'] = [
            'enable' => Request::int('mustFollow'),
        ];
    }

    if (App::isMoscaleEnabled()) {
        $extra['moscale'] = [
            'key' => Request::trim('moscaleMachineKey'),
            'label' => array_map(function ($e) {
                return intval($e);
            }, explode(',', Request::trim('moscaleLabel'))),
            'region' => [
                'province' => Request::int('province_code'),
                'city' => Request::int('city_code'),
                'area' => Request::int('area_code'),
            ],
        ];
    }

    if (App::isZeroBonusEnabled()) {
        setArray($extra, 'custom.bonus.zero.v', min(100, Request::float('zeroBonus', -1, 2)));
    }

    if (App::isFlashEggEnabled()) {
        setArray($extra, 'ad.device.uid', Request::trim('adDeviceUID'));
        $extra['limit'] = [
            'scname' => Request::trim('scname', Account::DAY),
            'count' => Request::int('count'),
            'sccount' => Request::int('sccount'),
            'total' => Request::int('total'),
            'all' => Request::int('all'),
        ];
    }

    if (empty($data['name']) || empty($data['imei'])) {
        throw new RuntimeException('设备名称或IMEI不能为空！');
    }

    $type_id = Request::int('deviceType');
    if ($type_id) {
        $device_type = DeviceTypes::get($type_id);
        if (empty($device_type)) {
            throw new RuntimeException('设备类型不正确！');
        }
    }

    $data['device_type'] = $type_id;

    $device_model = Request::str('device_model');

    if (App::isBluetoothDeviceSupported() && $device_model == Device::BLUETOOTH_DEVICE) {
        $extra['bluetooth'] = [
            'protocol' => Request::str('blueToothProtocol'),
            'uid' => Request::trim('BUID'),
            'mac' => Request::trim('MAC'),
            'motor' => Request::int('Motor'),
            'timeout' => Request::int('timeout'),
        ];
    }

    if (App::isFuelingDeviceEnabled() && $device_model == Device::FUELING_DEVICE) {
        $extra['pulse'] = Request::int('pulse');
        $extra['timeout'] = Request::int('timeout');
        $extra['solo'] = Request::bool('solo') ? 1 : 0;
        $extra['expiration'] = Request::str('expiration');
    }

    $agent_id = Request::int('agent_id');
    if ($agent_id) {
        $agent = Agent::get($agent_id);
        if (empty($agent)) {
            throw new RuntimeException('找不到这个代理商！');
        }

        $data['agent_id'] = $agent->getId();
    }

    //更新设备
    if ($id > 0) {
        $device = Device::get($id);
        if (empty($device)) {
            throw new RuntimeException('设备不存在！');
        }

        $device->setDeviceModel($device_model);

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
            $res = $device->resetPayload(['*' => '@0'], '管理员改变型号');
            if (is_error($res)) {
                throw new RuntimeException('保存库存失败！');
            }
            $device->setDeviceType($data['device_type']);
        }

        if ($data['group_id'] != $device->getGroupId()) {
            $device->setGroupId($data['group_id']);
        }

        if (Request::isset('volume')) {
            $vol = max(0, min(100, Request::int('volume')));
            if ($vol != $device->settings('extra.volume')) {
                $extra['volume'] = $vol;
            }
        }

        $device->setMaintenance($data['s3']);

    } else {
        if (Device::exists(['imei' => $data['imei']])) {
            throw new RuntimeException('设备IMEI已经存在！');
        }

        //创建新设备
        $device = Device::create($data);
        if (empty($device)) {
            throw new RuntimeException('创建失败！');
        }

        $device->setDeviceModel($device_model);

        if ($device->isNormalDevice() || $device->isChargingDevice() || $device->isFuelingDevice()) {
            $activeRes = Device::activate($device->getImei());
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

    //充电设备处理
    if ($device->isChargingDevice()) {
        $device->setDeviceType(0);

        $device_type = DeviceTypes::from($device);
        if (empty($device_type)) {
            throw new RuntimeException('设备类型不正确！');
        }
        $cargo_lanes = [];
        for ($i = 0; $i < Request::int('chargerNum'); $i++) {
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
            $capacities = Request::array('capacities');

            foreach (Request::array('goods') as $index => $goods_id) {
                $cargo_lanes[] = [
                    'goods' => intval($goods_id),
                    'capacity' => $device->isFuelingDevice() ? intval(round($capacities[$index] * 100)) : intval(
                        $capacities[$index]
                    ),
                    'auto' => Request::bool("lane{$index}_auto"),
                ];
                if ($old[$index] && $old[$index]['goods'] != intval($goods_id)) {
                    $device->resetPayload([$index => '@0'],
                        $device->isFuelingDevice() ? '管理员更改加注枪商品' : '管理员更改货道商品');
                }
                unset($old[$index]);
            }

            foreach ($old as $index => $lane) {
                $device->resetPayload([$index => '@0'],
                    $device->isFuelingDevice() ? '管理员删除加注枪' : '管理员删除货道');
            }

            $device_type->setExtraData('cargo_lanes', $cargo_lanes);
            $device_type->save();
        }
    }

    if (empty($device_type)) {
        throw new RuntimeException('获取型号失败！');
    }

    //货道商品数量和价格
    $type_data = DeviceTypes::format($device_type);
    $cargo_lanes = [];
    foreach ($type_data['cargo_lanes'] as $index => $lane) {
        if ($device->isFuelingDevice()) {
            $num = intval(Request::float("lane{$index}_num", 0, 2) * 100);
        } else {
            $num = Request::int("lane{$index}_num");
        }
        $cargo_lanes[$index] = [
            'num' => '@'.max(0, $num),
        ];
        if ($device_type->getDeviceId() == $device->getId()) {
            $cargo_lanes[$index]['price'] = Request::float("price$index", 0, 2) * 100;
        }
    }

    $res = $device->resetPayload($cargo_lanes, '管理员编辑设备');
    if (is_error($res)) {
        throw new RuntimeException('保存设备库存数据失败！');
    }

    $location = Request::array('location');
    $extra['location']['baidu']['lat'] = $location['lat'];
    $extra['location']['baidu']['lng'] = $location['lng'];

    $saved_baidu_loc = $device->settings('extra.location.baidu', []);
    if (strval($saved_baidu_loc['lng']) != strval($location['lng'])
        || strval($saved_baidu_loc['lat']) != strval($location['lat'])) {
        $address = LocationUtil::getData($location['lng'], $location['lat']);
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
    $extra['goodsList'] = App::isGoodsPackageEnabled() ? Request::trim('goodsList') : 'goods';

    $extra['schedule'] = [
        'screen' => [
            'enabled' => Request::bool('screenSchedule') ? 1 : 0,
            'on' => Request::str('start'),
            'off' => Request::str('end'),
        ],
    ];

    $original_extra = $device->get('extra', []);
    if ($original_extra['schedule']['screen'] !== $extra['schedule']['screen']) {
        $device->appPublishConfig([
            'schedule' => $extra['schedule']['screen'],
        ]);
    }

    //设备单独支付配置
    $extra['wx_v3'] = [
        'sub_mch_id' => Request::trim('sub_mch_id'),
    ];

    //合并extra
    $extra = array_merge($original_extra, $extra);

    if (!$device->set('extra', $extra)) {
        throw new RuntimeException('保存扩展数据失败！');
    }

    $tags = Request::trim('tags');
    $device->setTagsFromText($tags);

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
    } else {
        if (App::isZJBaoEnabled()) {
            $device->updateSettings('zjbao.scene', Request::trim('ZJBao_Scene'));
        }

        //更新公众号缓存
        $device->updateAccountData();
        $device->updateScreenAdsData();
        $device->updateAppVolume();
        $device->updateAppRemain();
    }

    $res = $device->updateQRCode(true);

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
    Response::toast($result['message'], $id ? We7::referer() : Util::url('device'), 'error');
}

if ($device) {
    if (App::isGDCVMachineEnabled()) {
        GDCVMachine::scheduleUploadDeviceJob($device);
    }

    if (App::isTKPromotingEnabled()) {
        TKPromoting::deviceReg($device);
    }

    if (!App::isDeviceScheduleTaskEnabled()) {
        $device->updateSettings('schedule', []);
    }

    if (App::isGoodsExpireAlertEnabled()) {
        $payload = $device->getPayload();
        if ($payload['cargo_lanes']) {

            $alertExpiredAt = Request::array('alertExpiredAt');
            $alertPreDays = Request::array('alertPreDays');
            $alertInvalid = Request::array('alertInvalid');

            $getExpiredTimestampFN = function ($index) use ($alertExpiredAt) {
                $expired_at = $alertExpiredAt[$index];
                if ($expired_at) {
                    try {
                        return (new DateTime($expired_at))->getTimestamp();
                    } catch (Exception $e) {
                    }
                }

                return 0;
            };

            foreach ((array)$payload['cargo_lanes'] as $index => $lane) {
                $alert = GoodsExpireAlert::getFor($device, $index);
                if ($alert) {
                    $alert->setAgentId($device->getAgentId());
                    $alert->setExpiredAt($getExpiredTimestampFN($index));
                    $alert->setPreDays(intval($alertPreDays[$index]));
                    $alert->setInvalidIfExpired($alertInvalid[$index] == '1');
                } else {
                    $alert = GoodsExpireAlert::create([
                        'agent_id' => $device->getAgentId(),
                        'device_id' => $device->getId(),
                        'lane_id' => $index,
                        'expired_at' => $getExpiredTimestampFN($index),
                        'pre_days' => intval($alertPreDays[$index]),
                        'invalid_if_expired' => $alertInvalid[$index] == '1',
                    ]);
                }

                $alert->save();
            }
        }
    }

    Helper::removeInvalidAlert($device);

    if (App::isDeviceLaneQRCodeEnabled()) {
        $device->createQRCodeFileForAllLanes();
    }
}

$redirect_url = Util::url('device', [
    'op' => 'edit',
    'id' => $device ? $device->getId() : $id,
    'from' => Request::str('from'),
]);

Response::toast($result['message'], $redirect_url, $result['error'] ? 'warning' : 'success');