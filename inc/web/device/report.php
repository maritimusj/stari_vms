<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$device = Device::get(Request::int('id'));
if ($device) {
    $code = $device->getProtocolV1Code();
    if ($code) {
        $device->reportMcbStatus($code);
    }
}