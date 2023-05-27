<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\upload_gv_info;

use zovye\Config;
use zovye\CtrlServ;
use zovye\Device;
use zovye\GDCVMachine;
use zovye\Log;
use zovye\Order;
use zovye\Request;
use function zovye\is_error;

$data = [
    'id' => Request::int('id'),
    'w' => Request::str('w'),
];

$op = Request::op('default');

if ($op == 'upload_gv_info' && CtrlServ::checkJobSign($data)) {

    $id = Request::int('id');
    $w = Request::str('w');

    if ($w == 'device') {
        $device = Device::get($id);
        if ($device) {

            $last_ts = Config::GDCVMachine('last.device_upload', 0);
            $delay = max(1, 60 - (time() - $last_ts));
            sleep($delay);

            $res = (new GDCVMachine())->uploadDeviceInfo($device);
            if (is_error($res)) {
                Log::error('upload_gv_info', [
                    'error' => $res,
                ]);
            }
            $data['result'] = $res;
        } else {
            $data['error'] = '找不到这个设备！';
        }

    } elseif ($w == 'types') {

        $list = [];

        $query = Device::query(['device_type' => $id]);
        foreach ($query->findAll() as $device) {
            $list[] = $device;
        }

        $last_ts = Config::GDCVMachine('last.device_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));
        sleep($delay);

        $res = (new GDCVMachine())->uploadDevicesInfo($list);
        if (is_error($res)) {
            Log::error('upload_gv_info', [
                'error' => $res,
            ]);
        }
        $data['result'] = $res;

    } elseif ($w == 'order') {

        $last_ts = Config::GDCVMachine('last.order_upload', 0);
        $delay = max(1, 60 - (time() - $last_ts));
        sleep($delay);

        $order = Order::get($id);
        if ($order) {
            $res = (new GDCVMachine())->uploadOrderInfo($order);
            if ($res) {
                Log::error('upload_gv_info', [
                    'error' => $res,
                ]);
            }
            $data['result'] = $res;
        } else {
            $data['error'] = '找不到这个订单！';
        }
    }
} else {
    $data['error'] = '签名不正确！';
}

Log::debug('upload_gv_info', $data);