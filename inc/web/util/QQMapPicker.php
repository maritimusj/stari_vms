<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$content = app()->fetchTemplate(
    'web/util/qq_map_picker',
    [
        'lbs_key' => settings('user.location.appkey', DEFAULT_LBS_KEY),
    ]
);

JSON::success(['title' => '', 'content' => $content]);