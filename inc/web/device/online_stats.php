<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

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

    $online_ids = [];
    /** @var deviceModelObj $entry */
    foreach ($devices as $entry) {
        if ($entry->isBlueToothDevice() || $entry->isVDevice()) {
            continue;
        }
        $online_ids['uid'][] = $entry->getImei();
    }

    $ids_str = json_encode($online_ids);
    $devices_status = Util::cachedCall(10, function () use ($ids_str) {
        $res = CtrlServ::postV2('detail', $ids_str);
        if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
            return $res['data'];
        }

        return [];
    }, $ids_str);

    /** @var deviceModelObj $entry */
    foreach ($devices as $entry) {
        $data = [
            'id' => $entry->getId(),
            'status' => [
                'mcb' => false,
                'app' => empty($entry->getAppId()) ? null : false,
            ],
        ];

        if ($entry->isVDevice() || $entry->isBlueToothDevice()) {
            $data['status']['mcb'] = true;
        } else {
            $status = $devices_status[$entry->getImei()];
            if (isset($status['mcb']['online'])) {
                $data['status']['mcb'] = boolval($status['mcb']['online']);
            }

            if (isset($status['app']['online'])) {
                $data['status']['app'] = boolval($status['app']['online']);
                if (!empty($status['app']['uid'])) {
                    $data['status']['appId'] = $status['app']['uid'];
                    $entry->setAppId($status['app']['uid']);
                }
            }

            if (isset($status['mcb']['RSSI'])) {
                $entry->setSig($status['mcb']['RSSI']);
                $data['sig'] = $entry->getSig();
            }

            $entry->save();
        }

        $result[] = $data;
    }
}

JSON::success($result);