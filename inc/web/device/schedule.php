<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\cronModelObj;

defined('IN_IA') or exit('Access Denied');

if (!App::isDeviceScheduleTaskEnabled()) {
    JSON::fail('功能没有启用！');
}

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$fn = Request::trim('fn');

if (empty($fn)) {

    $tpl_data = [
        'device' => $device,
        'payload' => $device->getPayload(true),
    ];

    Response::templateJSON('web/device/schedule', "定时出货 [ {$device->getName()} ]", $tpl_data);

} elseif ($fn == 'list') {

    $list = [];

    /** @var cronModelObj $entry */
    foreach (Device::getAllScheduleTask($device) as $entry) {
        $data = [
            'id' => $entry->getId(),
            'job_uid' => $entry->getJobUid(),
            'next' => Device::getScheduleTaskNext($entry->getJobUid()),
            'spec' => $entry->getSpec(),
            'createtime' => $entry->getCreatetime(),
        ];
        
        $list[] = $data;
    }

    JSON::success($list);

} elseif ($fn == 'create') {

    $spec = Request::trim('spec');

    if (empty($spec)) {
        JSON::fail('指定的计划任务不正确！');
    }

    $result = Device::createScheduleTask($device, $spec);
    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('创建成功！');

} elseif ($fn == 'remove') {

    $id = Request::int('id');

    $result = Device::deleteScheduleTask($id);
    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('删除成功！');
}

