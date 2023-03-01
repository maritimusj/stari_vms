<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$content = app()->fetchTemplate('web/user/export');

JSON::success(['title' => '用户导出', 'content' => $content]);