<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$url = WxPlatform::getPreAuthUrl();
if (empty($url)) {
    JSON::fail('暂时无法获取授权转跳网址！');
}

$content = app()->fetchTemplate(
    'web/account/authorize',
    [
        'url' => $url,
    ]
);

JSON::success(['title' => "公众号接入授权", 'content' => $content]);