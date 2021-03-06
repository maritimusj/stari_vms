<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'QQMapPicker') {

    $content = app()->fetchTemplate(
        'web/util/QQMapPicker',
        [
            'lbs_key' => settings('user.location.appkey', DEFAULT_LBS_KEY),
        ]
    );

    JSON::success(['title' => '', 'content' => $content]);
}