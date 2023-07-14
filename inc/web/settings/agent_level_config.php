<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$level = Request::str('id');

Response::templateJSON(
    'web/settings/agent_level',
    '代理商等级配置',
    [
        'level' => $level,
    ]
);