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
        'api_url' => Util::url('device', [
            'op' => 'schedule',
            'id' => $device->getId(),
        ]),
    ];

    Response::templateJSON('web/device/schedule', "定时出货 [ {$device->getName()} ]", $tpl_data);

} elseif ($fn == 'list') {

    $list = [];

    /** @var cronModelObj $entry */
    foreach (Device::getAllScheduleTask($device) as $entry) {
        $spec = $entry->getSpec();
        
        $data = [
            'id' => $entry->getId(),
            'spec' => $spec,
            'desc' => Cron::describe($spec),
            'total' => $entry->getTotal(),
            'job_uid' => $entry->getJobUid(),
            'next' => Device::getScheduleTaskNext($entry->getJobUid()),
            'formatted_createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
        
        $list[] = $data;
    }

    JSON::success($list);

} elseif ($fn == 'create') {

    $tpl_data = [
        'device' => $device,
    ];

    Response::templateJSON('web/device/schedule_new', "创建定时任务 [ {$device->getName()} ]", $tpl_data);

} elseif ($fn == 'save') {

    if (Request::has('spec')) {
        $spec = Request::trim('spec');

        if (empty($spec)) {
            JSON::fail('指定的定时任务表达式不正确！');
        }    
    } else {
        $hour = Request::isset('hour') ? min(23, max(0, Request::int('hour'))) : '*';
        $minute = Request::isset('minute') ? min(59, max(0, Request::int('minute'))) : '*';
        $second = Request::isset('second') ? min(59, max(0, Request::int('second'))) : '*';
        $spec = "$second $minute $hour * * *";
    }

    $result = Device::createScheduleTask($device, $spec);
    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('创建成功！');

} elseif ($fn == 'remove') {

    $id = Request::int('tid');

    $result = Device::deleteScheduleTask($id);
    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('删除成功！');
}

