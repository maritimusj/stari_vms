<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$config = settings('device.upload', []);
if (empty($config['url'])) {
    JSON::fail('没有配置第三方平台！');
}

if (Job::uploadDeviceInfo()) {
    JSON::success('已启动设备上传任务！');
}

JSON::fail('设备信息上传任务启动失败！');