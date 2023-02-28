<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$result = Util::createQrcodeFile('deviceNearby', Util::murl('util'));

if (is_error($result)) {
    JSON::fail('创建二维码文件失败！');
}

$content = app()->fetchTemplate('web/common/qrcode', [
    'title' => '用微信扫一扫，打开附近设备',
    'url' => Util::toMedia($result),
]);

JSON::success([
    'title' => '附近设备',
    'content' => $content,
]);