<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if ($device) {
    $code = $device->getProtocolV1Code();
    if ($code) {
        $device->reportMcbStatus($code);
    }
}