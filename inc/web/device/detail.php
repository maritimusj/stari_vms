<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;
use zovye\model\packageModelObj;

$device = Device::get(Request::int('id'));
if (empty($device)) {
    Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
}

$tpl_data['navs'] = [
    'detail' => $device->getName(),
    'payload' => '库存',
    'log' => '事件',
    //'poll_event' => '最新',
    'event' => '消息',
];

if ($device->isChargingDevice()) {

    unset($tpl_data['navs']['payload']);
    unset($tpl_data['navs']['log']);

} elseif ($device->isFuelingDevice()) {

    $tpl_data['payload'] = $device->getPayload(true);

} else {
    $tpl_data['media'] = [
        'image' => ['title' => '图片'],
        'video' => ['title' => '视频'],
        'audio' => ['title' => '音频'],
        'srt' => ['title' => '字幕'],
    ];

    $accounts = $device->getAssignedAccounts();
    if ($accounts) {
        foreach ($accounts as &$entry) {
            $entry['edit_url'] = $this->createWebUrl('account', ['op' => 'edit', 'id' => $entry['id']]);
            if (empty($entry['qrcode'])) {
                $entry['qrcode'] = MODULE_URL.'static/img/qrcode_blank.svg';
            }
        }
    }
    $tpl_data['accounts'] = $accounts;

    $tpl_data['payload'] = $device->getPayload(true);

    $packages = [];
    $query = Package::query(['device_id' => $device->getId()]);
    /** @var packageModelObj $i */
    foreach ($query->findAll() as $i) {
        $packages[] = $i->format(true);
    }

    $tpl_data['packages'] = $packages;
}

$res = Device::getAppConfigData($device);
if (is_error($res)) {
    $tpl_data['config'] = false;
} else {
    if ($res['data']['srt']['subs']) {
        $srt = [];
        $ads = $device->getAds(Advertising::SCREEN);
        foreach ($ads as $ad) {
            if ($ad['extra']['media'] == 'srt') {
                $srt[] = [
                    'id' => $ad['id'],
                    'text' => strval($ad['extra']['text']),
                ];
            }
        }
        $tpl_data['srt'] = $srt;
    }
    $tpl_data['config'] = $res;
}

$tpl_data['is_device_notify_timeout'] = $device->isDeviceNotifyTimeout();
$tpl_data['last_error_notify'] = $device->settings('lastErrorNotify');
$tpl_data['last_error_data'] = $device->settings('lastErrorData');

$tpl_data['is_last_remain_warning_timeout'] = $device->isLastRemainWarningTimeout();
$tpl_data['last_remain_warning'] = $device->settings('lastRemainWarning');

$tpl_data['last_apk_update'] = $device->settings('lastApkUpdate');
$tpl_data['first_msg_statistic'] = $device->settings('firstMsgStatistic');
$tpl_data['first_total'] = intval($tpl_data['firstMsgStatistic'][date('Ym')][date('d')]['total']);

$tpl_data['day_stats'] = app()->fetchTemplate(
    'web/device/stats',
    [
        'chartid' => Util::random(10),
        'chart' => Util::cachedCall(30, function () use ($device) {
            return Stats::chartDataOfDay($device, new DateTime());
        }, $device->getId()),
    ]
);

$tpl_data['month_stats'] = app()->fetchTemplate(
    'web/device/stats',
    [
        'chartid' => Util::random(10),
        'chart' => Util::cachedCall(30, function () use ($device) {
            return Stats::chartDataOfMonth($device, new DateTime());
        }, $device->getId()),
    ]
);

$tpl_data['device'] = $device;
$tpl_data['mcb_online'] = $device->isMcbOnline();
$tpl_data['app_online'] = $device->isAppOnline();

app()->showTemplate('web/device/detail', $tpl_data);