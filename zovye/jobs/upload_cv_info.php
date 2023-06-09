<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\upload_cv_info;

use zovye\Config;
use zovye\CtrlServ;
use zovye\GDCVMachine;
use zovye\Log;
use zovye\model\cv_upload_deviceModelObj;
use zovye\model\cv_upload_orderModelObj;
use zovye\Request;
use function zovye\m;

$data = [
    'w' => Request::str('w'),
];

$op = Request::op('default');

if ($op == 'upload_cv_info' && CtrlServ::checkJobSign($data)) {

    $w = Request::str('w');

    if ($w == 'device') {
        $list = [];
        /** @var cv_upload_deviceModelObj $entry */
        foreach (m('cv_upload_device')->findAll() as $entry) {
            $device = $entry->getDevice();
            if ($device) {
                $list[] = $device;
            }

            $entry->destroy();
        }

        $list = array_values($list);

        if ($list) {
            $last_ts = Config::GDCVMachine('last.device_upload', 0);
            $delay = min(0, max(1, 65 - (time() - $last_ts)));
            sleep($delay);

            $response = (new GDCVMachine())->uploadDevicesInfo($list);
            if ($response) {
                $data['response'] = $response;
                Config::GDCVMachine('last.device_upload', time(), true);
            }
        }

    } elseif ($w == 'order') {
        $list = [];
        /** @var cv_upload_orderModelObj $entry */
        foreach (m('cv_upload_order')->findAll() as $entry) {
            $order = $entry->getOrder();
            if ($order) {
                $list[$order->getId()] = $order;
            }
            $entry->destroy();
        }

        $list = array_values($list);

        if ($list) {
            $last_ts = Config::GDCVMachine('last.order_upload', 0);
            $delay = min(0, max(1, 65 - (time() - $last_ts)));
            sleep($delay);

            $response = (new GDCVMachine())->uploadOrdersInfo($list);
            if ($response) {
                $data['response'] = $response;
                Config::GDCVMachine('last.order_upload', time(), true);
                if (!empty($response)) {
                    /** @var cv_upload_orderModelObj $order */
                    foreach ($list as $index => $order) {
                        $result = $response[$index] ?? ['code' => -1, 'message' => '返回数据为空或者错误！'];
                        if ($order) {
                            $order->setExtraData('CV.upload', $result);
                            $order->save();
                        }
                    }
                }
            }
        }
    }
} else {
    $data['error'] = '签名不正确！';
}

Log::debug('upload_cv_info', $data);