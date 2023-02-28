<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;

$tpl_data['navs'] = Util::getSettingsNavs();

$tpl_data['is_locked'] = app()->isLocked();
$tpl_data['cb_url'] = Util::getCtrlServCallbackUrl();
$tpl_data['navs']['ctrl'] = '高级设置';

$res = CtrlServ::query();
if (!is_error($res)) {
    $data = empty($res['data']) ? $res : $res['data'];

    $tpl_data['version'] = $data['version'] ?: 'n/a';
    $tpl_data['build'] = $data['build'] ?: 'n/a';

    if ($data['start']) {
        $tpl_data['formatted_duration'] = Util::getFormattedPeriod($data['start']);
    } else {
        if ($data['startTime']) {
            $tpl_data['formatted_duration'] = Util::getFormattedPeriod($data['startTime']);
        }
    }

    if ($data['now']) {
        $tpl_data['formatted_now'] = (new DateTime())->setTimestamp($data['now'])->format("Y-m-d H:i:s");
    }
    $tpl_data['queue'] = Config::app('queue', []);
}

if (App::isChargingDeviceEnabled()) {
    $tpl_data['charging'] = [
        'server' => Config::charging('server', []),
    ];

    $res = ChargingServ::GetVersion();
    if (is_error($res)) {
        $tpl_data['charging']['server']['version'] = 'n/a';
    } else {
        $tpl_data['charging']['server']['version'] = $res['version'];
        $tpl_data['charging']['server']['build'] = $res['build'];
    }
}

$tpl_data['migrate'] = Migrate::detect();

$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/user', $tpl_data);
