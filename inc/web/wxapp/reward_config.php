<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$reward = Config::app('wxapp.advs.reward', []);
$content = app()->fetchTemplate(
    'web/settings/reward_config',
    [
        'config' => $reward,
    ]
);

JSON::success(['content' => $content]);