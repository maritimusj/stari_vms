<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\inventory_goodsModelObj;

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$inventory = Inventory::for($user);
if (empty($inventory)) {
    JSON::fail('无法打开该用户的库存数据！');
}

$tpl_data = [
    'title' => $inventory->getTitle(),
    'user' => $user->getId(),
    'id' => $inventory->getId(),
];

$query = $inventory->query();

if (Request::has('agentId')) {
    $agent = Agent::get(Request::int('agentId'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }
    $query->where(['agent_id' => $agent->getId()]);
}

//搜索关键字
$keywords = Request::trim('keywords');
if ($keywords) {
    $query->whereOr([
        'name LIKE' => "%$keywords%",
    ]);
}

$total = $query->count();
$list = [];

if ($total > 0) {
    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id ASC');

    /** @var inventory_goodsModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $goods = $entry->getGoods();
        if ($goods) {
            $list[] = [
                'goods' => Goods::format($goods, true, true),
                'num' => $entry->getNum(),
            ];
        }
    }
}

$tpl_data['list'] = $list;

if (Request::is_ajax()) {
    $content = app()->fetchTemplate('web/inventory/choose', [
        'list' => $list,
        'pager' => $tpl_data['pager'],
        'backer' => (bool)$keywords,
    ]);

    JSON::success([
        'title' => '选择商品',
        'content' => $content,
    ]);
}

app()->showTemplate('web/inventory/detail', $tpl_data);