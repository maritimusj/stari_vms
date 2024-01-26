<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//简单的二维码导出功能
use ZipArchive;
use zovye\domain\Device;
use zovye\model\deviceModelObj;
use zovye\util\Util;

$url_prefix = We7::attachment_set_attach_url();
$attach_prefix = ATTACHMENT_ROOT;

$zip = new ZipArchive();
$file_name = time().'_'.rand().'.zip';
$file_path = $attach_prefix.$file_name;
$zip->open($file_path, ZipArchive::CREATE);   //打开压缩包

$ids = Request::array('ids');
$query = Device::query(['id' => $ids]);
$fn = Request::trim('fn');

$addFile = function ($url) use ($zip, $url_prefix, $attach_prefix) {
    $filename = str_replace($url_prefix, $attach_prefix, $url);
    $filename = preg_replace('/\?.*/', '', $filename);
    if (file_exists($filename)) {
        $zip->addFile($filename, basename($filename));
    }
};

/** @var deviceModelObj $device */
foreach ($query->findAll() as $device) {
    if ($device->isChargingDevice()) {
        $chargerNum = $device->getChargerNum();
        for ($i = 0; $i < $chargerNum; $i++) {
            $url = Util::toMedia($device->getChargerProperty($i + 1, 'qrcode', ''));
            $addFile($url);
        }
    } else {
        if (empty($fn)) {
            $addFile($device->getQrcode());
        } elseif ($fn == 'lanes') {
            $payload = $device->getPayload(true);
            foreach((array)$payload['cargo_lanes'] as $lane) {
                if ($lane['qrcode_url']) {
                    $addFile($lane['qrcode_url']);    
                }
            }
        }
    }
}

$zip->close();

JSON::success(['url' => Util::toMedia($file_name, false, true)]);