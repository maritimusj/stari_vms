<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$result = QRCodeUtil::createFile('testing', Util::murl('testing'));

if (is_error($result)) {
    JSON::fail('创建二维码文件失败！');
}

Response::templateJSON('web/common/qrcode',
    '测试入口',
    [
    'title' => '用微信扫一扫，打开测试页面',
    'url' => Util::toMedia($result),
]);