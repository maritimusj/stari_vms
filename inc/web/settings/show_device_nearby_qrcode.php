<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\util\QRCodeUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$result = QRCodeUtil::createFile('deviceNearby', Util::murl('util'));

if (is_error($result)) {
    JSON::fail('创建二维码文件失败！');
}

Response::templateJSON('web/common/qrcode',
    '附近设备',
    [
    'title' => '用微信扫一扫，打开附近设备',
    'url' => Util::toMedia($result),
]);