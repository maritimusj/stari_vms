<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$result = QRCodeUtil::createFile('agent', Util::murl('mobile'));

if (is_error($result)) {
    JSON::fail('创建二维码文件失败！');
}

$content = app()->fetchTemplate('web/common/qrcode', [
    'title' => '用微信扫一扫，打开代理商注册页面',
    'url' => Util::toMedia($result),
]);

JSON::success([
    'title' => '代理商注册页面',
    'content' => $content,
]);