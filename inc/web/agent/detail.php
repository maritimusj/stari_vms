<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$pages = [
    'default' => [
        'title' => '首页',
    ],
    'devices' => [
        'title' => '设备列表',
    ],
    'accounts' => [
        'title' => '吸粉广告',
    ],
    'advs' => [
        'title' => '其它广告',
    ],
    'orders' => [
        'title' => '订单列表',
    ],
    'commission' => [
        'title' => '佣金明细',
    ],
    'withdraw' => [
        'title' => '提现记录',
    ],
    'partner' => [
        'title' => '合伙人',
    ],
    'keepers' => [
        'title' => '运营人员',
    ],
    'statistics' => [
        'title' => '统计数据',
    ],
];

$id = request::int('id');
$agent = Agent::get($id);
if (empty($agent)) {
    Util::itoast('找不到这个代理商！', 'error');
}

$page_name = request::trim('page_name', 'default');

app()->showTemplate("web/agent/detail/$page_name", [
    'agent' => $agent,
    'pages' => $pages,
    'id' => $id,
    'page_name' => $page_name,
]);