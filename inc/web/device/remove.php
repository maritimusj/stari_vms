<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\business\ChargingNowData;
use zovye\domain\Device;
use zovye\domain\Package;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

try {
    $device = Device::get(Request::int('id'));
    if (empty($device)) {
        throw new RuntimeException('找不到这个设备！');
    }
    
    We7::load()->func('file');
    We7::file_remote_delete($device->getQrcode());
    
    $device->remove('assigned');
    $device->remove('adsData');
    $device->remove('accountsData');
    $device->remove('lastErrorNotify');
    $device->remove('lastRemainWarning');
    $device->remove('fakeQrcodeData');
    $device->remove('lastApkUpdate');
    $device->remove('firstMsgStatistic');
    $device->remove('location');
    $device->remove('statsData');
    $device->remove('lastErrorData');
    $device->remove('extra');
    
    if ($device->isChargingDevice()) {
        ChargingNowData::removeAllByDevice($device);
    }
    
    //删除相关套餐
    foreach (Package::query(['device_id' => $device->getId()])->findAll() as $entry) {
        $entry->destroy();
    }
    
    if ($device->isNormalDevice()) {
        $imei = $device->getImei();
        if (!empty($imei)) {
            $res = Device::release($imei);
            if (is_error($res)) {
                Log::error('device', [
                    'imei' => $imei,
                    'message' => '释放设备失败！',
                    'result' => $res,
                ]);
            }
        }
    }
    
    //通知实体设备
    $device->appNotify('update');
    
    $device->destroy();
    
    if (Request::is_ajax()) {
        JSON::success(['msg' => '删除成功！']);
    }
    
    Response::toast('删除成功！', Util::url('device'), 'success');

} catch(Exception $e) {
    
    if (Request::is_ajax()) {
        JSON::fail(['msg' => $e->getMessage()]);
    } else {
        Response::toast($e->getMessage(), Util::url('device'), 'error');
    }
}
