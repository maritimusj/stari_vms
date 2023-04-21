<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$content = app()->fetchTemplate('web/common/stats');

JSON::success(['title' => '积分第三方接口统计', 'content' => $content]);