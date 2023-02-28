<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$level = request::str('id');

$content = app()->fetchTemplate(
    'web/settings/agent_level',
    [
        'level' => $level,
    ]
);

JSON::success(['title' => '代理商等级配置', 'content' => $content]);