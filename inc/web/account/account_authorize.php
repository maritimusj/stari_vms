<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$url = WxPlatform::getPreAuthUrl();
if (empty($url)) {
    JSON::fail('暂时无法获取授权转跳网址！');
}

Response::templateJSON(
    'web/account/authorize',
    '公众号接入授权',
    [
        'url' => $url,
    ]
);