<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$result = Util::createQrcodeFile('testing', Util::murl('testing'));

if (is_error($result)) {
    JSON::fail('创建二维码文件失败！');
}

$content = app()->fetchTemplate('web/common/qrcode', [
    'title' => '用微信扫一扫，打开测试页面',
    'url' => Util::toMedia($result),
]);

JSON::success([
    'title' => '测试入口',
    'content' => $content,
]);