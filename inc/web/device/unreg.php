<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $device = Device::get($id);
    if ($device) {
        $app_id = $device->getAppId();
        if ($device->setAppId(null) && $device->setAppVersion(null) && $device->save()) {

            //删除广告缓存
            $device->remove('adsData');

            //通知app更新配置
            if ($app_id) {
                CtrlServ::appNotify($app_id, 'update');
            }

            Response::itoast('清除AppId成功！', $this->createWebUrl('device'), 'success');
        }
    }
}

Response::itoast('清除AppId失败！', $this->createWebUrl('device'), 'error');