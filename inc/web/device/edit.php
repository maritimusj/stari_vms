<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\MoscaleAccount;
use zovye\account\ZhiJinBaoAccount;
use zovye\model\device_groupsModelObj;

$id = Request::int('id');
$device_types = [];

if ($id) {
    $device = Device::get($id);
    if (empty($device)) {
        Util::itoast('设备不存在！', We7::referer(), 'error');
    }

    $x = DeviceTypes::from($device);
    if ($x) {
        $device_types[] = DeviceTypes::format($x);
    }

    $tpl_data['app'] = $device->getAppId();

    $extra = $device->get('extra');

    $loc = empty($extra['location']['baidu']) ? [] : $extra['location']['baidu'];
    $tpl_data['loc'] = $loc;

    $tpl_data['device'] = $device;
    $tpl_data['extra'] = $extra;

    if ($device->isBlueToothDevice()) {
        $tpl_data['bluetooth'] = $device->settings('extra.bluetooth', []);
    }

    $model = $device->getDeviceModel();

    $agent = $device->getAgent();
    $tpl_data['agent'] = $agent;

} else {

    $model = Request::str('model');
    if ($model == 'vd') {
        $tpl_data['vd_imei'] = 'V'.Util::random(15, true);
    }

}

$tpl_data['device_model'] = $model;

$tpl_data['bluetooth']['protocols'] = BlueToothProtocol::all();
$tpl_data['device_types'] = $device_types;

if (isset($device) && App::isMoscaleEnabled() && MoscaleAccount::isAssigned($device)) {
    $tpl_data['moscale'] = [
        'MachineKey' => isset($extra) && is_array($extra) ? strval($extra['moscale']['key']) : '',
        'LabelList' => MoscaleAccount::getLabelList(),
        'AreaListSaved' => isset($extra) && is_array($extra) ? $extra['moscale']['label'] : [],
        'RegionData' => MoscaleAccount::getRegionData(),
        'RegionSaved' => isset($extra) && is_array($extra) ? $extra['moscale']['region'] : [],
    ];
}

if (isset($device) && App::isZJBaoEnabled() && ZhiJinBaoAccount::isAssigned($device)) {
    $tpl_data['zjbao'] = [
        'scene' => $device->settings('zjbao.scene', ''),
    ];
}

$module_url = MODULE_URL;
if ($model == Device::VIRTUAL_DEVICE) {
    $icon_html = <<<HTML
    <img src="{$module_url}static/img/vdevice.svg" class="icon" title="虚拟设备">
HTML;
} elseif ($model == Device::BLUETOOTH_DEVICE) {
    $icon_html = <<<HTML
    <img src="{$module_url}static/img/bluetooth.svg" class="icon" title="蓝牙设备">
HTML;
} elseif ($model == Device::CHARGING_DEVICE) {
    $icon_html = <<<HTML
    <img src="{$module_url}static/img/charging.svg" class="icon" title="充电桩">
HTML;
} elseif ($model == Device::FUELING_DEVICE) {
    $icon_html = <<<HTML
    <img src="{$module_url}static/img/fueling.svg" class="icon" title="尿素加注设备">
HTML;
} else {
    $icon_html = <<<HTML
    <img src="{$module_url}static/img/machine.svg" class="icon">
HTML;
}

$groups = [];
if ($model == Device::CHARGING_DEVICE) {
    $group_query = Group::query(Group::CHARGING);
} else {
    $group_query = Group::query(Group::NORMAL);
}

/** @var device_groupsModelObj $val */
foreach ($group_query->findAll() as $val) {
    $groups[$val->getId()] = ['title' => $val->getTitle()];
}

$tpl_data['groups'] = $groups;
$tpl_data['icon'] = $icon_html;
$tpl_data['from'] = Request::str('from', 'base');
$tpl_data['themes'] = Theme::all();

$tpl_data['is_normal_device'] = $model == Device::NORMAL_DEVICE;
$tpl_data['is_bluetooth_device'] = $model == Device::BLUETOOTH_DEVICE;
$tpl_data['is_vdevice'] = $model == Device::VIRTUAL_DEVICE;
$tpl_data['is_charging_device'] = $model == Device::CHARGING_DEVICE;
$tpl_data['is_fueling_device'] = $model == Device::FUELING_DEVICE;

app()->showTemplate('web/device/edit_new', $tpl_data);