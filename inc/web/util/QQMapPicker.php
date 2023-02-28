<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$content = app()->fetchTemplate(
    'web/util/qq_map_picker',
    [
        'lbs_key' => settings('user.location.appkey', DEFAULT_LBS_KEY),
    ]
);

JSON::success(['title' => '', 'content' => $content]);