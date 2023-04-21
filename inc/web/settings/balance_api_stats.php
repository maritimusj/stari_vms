<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$title = '';

$content = app()->fetchTemplate('web/common/stats', [
    'chartid' => Util::random(10),
    'title' => $title,
    'chart' => Stats::getBalanceApiStats($title),
]);

JSON::success(['title' => '接口积分统计', 'content' => $content]);