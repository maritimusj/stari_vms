<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$reward = Config::app('wxapp.advs.reward', []);
Response::templateJSON(
    'web/settings/reward_config',
    '',
    [
        'config' => $reward,
    ]
);