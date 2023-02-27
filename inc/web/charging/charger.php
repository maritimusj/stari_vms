<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
$device = Device::get($id);
if (!$device || !$device->isChargingDevice()) {
    JSON::fail('设备不正确！');
}

$result = [];

$chargerNum = $device->getChargerNum();

$spanFN = function ($str) {
    return '<span class="val">'.$str.'</span>';
};

for ($i = 0; $i < $chargerNum; $i++) {
    $chargerData = $device->getChargerStatusData($i + 1);

    $data = [
        'status' => 'unknown',
        'properties' => [],
        'qrcode' => Util::toMedia($device->getChargerProperty($i + 1, 'qrcode', '')) . '?v=' . time(),
        'errors' => $chargerData['errorBits'] || [],
    ];

    $status = '未知';
    switch ($chargerData['status']) {
        case 0:
            $status = '<span class="title">离线</span>';
            $data['status'] = 'offline';
            break;
        case 1:
            $status = '<span class="title">故障</span>';
            $data['status'] = 'malfunction';
            break;
        case 2:
            $status = '<span class="title">空闲</span>';
            $data['status'] = 'idle';
            break;
        case 3:
            $status = '<span class="title">充电中</span> ';
            $data['status'] = 'charging';
            break;
    }

    $data['properties'][] = [
        'title' => '状态',
        'val' => '<div class="charger-status operate">'.$status.'<div>',
    ];

    $parked = '未知';
    switch ($chargerData['parked']) {
        case 0:
            $parked = '否';
            break;
        case 1:
            $parked = '是';
            break;
    }
    $data['properties'][] = [
        'title' => '枪是否归位',
        'val' => $parked,
    ];

    $plugged = '未知';
    switch ($chargerData['plugged']) {
        case 0:
            $plugged = '否';
            break;
        case 1:
            $plugged = '是';
            break;
    }
    $data['properties'][] = [
        'title' => '是否插枪',
        'val' => $plugged,
    ];
    $data['properties'][] = [
        'title' => '输出电压',
        'val' => $spanFN(floatval($chargerData['outputVoltage'])).'V',
    ];
    $data['properties'][] = [
        'title' => '输出电流',
        'val' => $spanFN(floatval($chargerData['outputCurrent'])).'A',
    ];
    $data['properties'][] = [
        'title' => '枪线编码',
        'val' => strval($chargerData['chargerWireUID']),
    ];
    $data['properties'][] = [
        'title' => '枪线温度',
        'val' => $spanFN(floatval($chargerData['chargerWireTemp'])).'°C',
    ];
    $data['properties'][] = [
        'title' => 'SOC',
        'val' => $spanFN(intval($chargerData['soc'])).'%',
    ];
    $data['properties'][] = [
        'title' => '电池组最高温度',
        'val' => $spanFN(floatval($chargerData['batteryMaxTemp'])).'°C',
    ];
    $data['properties'][] = [
        'title' => '累计充电时间',
        'val' => $spanFN(intval($chargerData['timeTotal'])).'分',
    ];
    $data['properties'][] = [
        'title' => '剩余时间',
        'val' => $spanFN(intval($chargerData['timeRemain'])).'分',
    ];
    $data['properties'][] = [
        'title' => '充电度数',
        'val' => $spanFN(floatval($chargerData['chargedKWH'])).'kW·h',
    ];
    $data['properties'][] = [
        'title' => '已充金额',
        'val' => $spanFN(floatval($chargerData['priceTotal'])).'元',
    ];
    $data['properties'][] = [
        'title' => '硬件故障',
        'val' => intval($chargerData['error']),
    ];

    $result[] = $data;
}

JSON::success($result);