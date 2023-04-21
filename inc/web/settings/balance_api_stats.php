<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$title = '第三方api积分统计';

$content = app()->fetchTemplate('web/common/stats', [
    'chartid' => Util::random(10),
    'title' => $title,
    'chart' => Stats::getBalanceApiStats($title),
]);

JSON::success(['title' => '积分第三方接口统计', 'content' => $content]);