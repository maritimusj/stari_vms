<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$reward = Config::app('wxapp.advs.reward', []);
$content = app()->fetchTemplate(
    'web/settings/reward_config',
    [
        'config' => $reward,
    ]
);

JSON::success(['content' => $content]);