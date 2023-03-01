<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$goods = Goods::get(Request::int('id'));
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

$title = date('n月d日');
$data = Stats::chartDataOfDay($goods, new DateTime(), "商品：{$goods->getName()}($title)");

$content = app()->fetchTemplate(
    'web/goods/stats',
    [
        'chartid' => Util::random(10),
        'title' => $title,
        'chart' => $data,
    ]
);

JSON::success(['z' => date('z'), 'content' => $content]);