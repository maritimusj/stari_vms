<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\deviceModelObj;

$ids = Request::has('id') ? [Request::int('id')] : request('ids');

if (is_string($ids)) {
    $ids = explode(',', $ids);
}

$result = [];

if (is_array($ids)) {
    foreach ($ids as &$id) {
        $id = intval($id);
    }

    $devices = Device::query(['id' => $ids])->findAll();

    /** @var deviceModelObj $device */
    foreach ($devices as $device) {
        $data = [
            'id' => $device->getId(),
            'name' => $device->getName(),
            'IMEI' => $device->getImei(),
            'ICCID' => $device->getICCID(),
            'qrcode' => $device->getQrcode(),
            'model' => $device->getDeviceModel(),
            'activeQrcode' => $device->isActiveQrcodeEnabled(),
            'getUrl' => $device->getUrl(),
            'v0_status' => [
                Device::V0_STATUS_SIG => $device->getSig(),
                Device::V0_STATUS_QOE => $device->getQoe(),
                Device::V0_STATUS_VOLTAGE => $device->getV0Status(Device::V0_STATUS_VOLTAGE),
                Device::V0_STATUS_COUNT => (int)$device->getV0Status(Device::V0_STATUS_COUNT),
                Device::V0_STATUS_ERROR => $device->getV0ErrorDescription(),
            ],
            'capacity' => $device->getCapacity(),
            'remain' => $device->getRemainNum(),
            'reset' => $device->getReset(),
            'lastError' => $device->getLastError(),
            'lastOnlineIp' => $device->getLastOnlineIp(),
            'lastOnline' => $device->getLastOnline() ? date('Y-m-d H:i:s', $device->getLastOnline()) : '',
            'lastPing' => $device->getLastPing() ? date('Y-m-d H:i:s', $device->getLastPing()) : '',
            'createtime' => date('Y-m-d H:i:s', $device->getCreatetime()),
            'lockedTime' => $device->isLocked() ? date('Y-m-d H:i:s', $device->getLockedTime()) : '',
            'appId' => $device->getAppId(),
            'appVersion' => $device->getAppVersion(),
            'total' => 'n/a',
            'gettype' => [
                'location' => $device->needValidateLocation(),
            ],
            'address' => [
                'web' => $device->settings('extra.location.baidu.address', ''),
                'agent' => $device->settings('extra.location.tencent.address', ''),
            ],
            'isDown' => $device->settings('extra.isDown', Device::STATUS_NORMAL),
        ];

        if (App::isDeviceWithDoorEnabled()) {
            if (!$device->isChargingDevice() && !$device->isFuelingDevice()) {
                $data['doorNum'] = $device->getDoorNum();
            }
        }

        if (Util::isSysLoadAverageOk()) {
            if ($device->isFuelingDevice()) {
                $data['total'] = [
                    'month' => floatval(Stats::getMonthTotal($device)['total'] / 100),
                    'today' => floatval(Stats::getDayTotal($device)['total'] / 100),
                ];
            } else {
                $data['total'] = [
                    'month' => intval(Stats::getMonthTotal($device)['total']),
                    'today' => intval(Stats::getDayTotal($device)['total']),
                ];
            }

            $data['gettype']['freeLimitsReached'] = $device->isFreeLimitsReached();

            $accounts = $device->getAssignedAccounts();
            if ($accounts) {
                $data['gettype']['free'] = true;
            }

            $payload = $device->getPayload(true);
            $data = array_merge($data, $payload);

            $low_price = 0;
            $high_price = 0;

            foreach ((array)$payload['cargo_lanes'] as $lane) {
                $goods_data = Goods::data($lane['goods'], ['useImageProxy' => true]);
                if ($goods_data && $goods_data[Goods::AllowPay]) {
                    if ($low_price === 0 || $low_price > $goods_data['price']) {
                        $low_price = $goods_data['price'];
                    }
                    if ($high_price == 0 || $high_price < $goods_data['price']) {
                        $high_price = $goods_data['price'];
                    }
                }
            }

            if ($low_price == $high_price) {
                $data['gettype']['price'] = number_format($low_price / 100, 2);
            } else {
                $data['gettype']['price'] = number_format($low_price / 100, 2).'-'.number_format(
                        $high_price / 100,
                        2
                    );
            }
        }

        $group = $device->getGroup();
        if ($group) {
            $data['group'] = $group->format();
        }

        $tags = $device->getTagsAsText(false);
        foreach ($tags as $i => $title) {
            $data['tags'][] = [
                'id' => $i,
                'title' => $title,
            ];
        }

        if (App::isVDeviceSupported() && $device->isVDevice()) {
            $data['isVD'] = true;
            unset($data['lastOnline'], $data['lastPing'], $data['lastError']);
        }

        if (App::isBluetoothDeviceSupported() && $device->isBlueToothDevice()) {
            $data['isBluetooth'] = true;
            $data['BUID'] = $device->getBUID();
        }

        if (App::isChargingDeviceEnabled() && $device->isChargingDevice()) {
            $data['isCharging'] = true;
            $data['charging'] = $device->getChargingData();
        }

        if (App::isFuelingDeviceEnabled() && $device->isFuelingDevice()) {
            $data['isFueling'] = true;
        }

        if (settings('device.lac.enabled')) {
            $data['s1'] = $device->getS1();
        }

        $data['device_type'] = $device->getDeviceType();

        $statistic = $device->get('firstMsgStatistic', []);
        if ($statistic) {
            $data['firstMsgTotal'] = intval($statistic[date('Ym')][date('d')]['total']);
        }

        $result[] = $data;
    }
}

JSON::success($result);
