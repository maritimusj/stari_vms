<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\inventory_logModelObj;

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

$query = $inventory->logQuery();

if (Request::isset('src')) {
    $query->where(['src_inventory_id' => Request::int('src')]);
}

if (Request::has('goods')) {
    $query->where(['goods_id' => Request::int('goods')]);
}

$total = $query->count();
$list = [];

if ($total > 0) {
    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    /** @var inventory_logModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'num' => $entry->getNum(),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
        $src = $entry->getSrcInventory();
        if ($src) {
            $data['src'] = $src->format();
        }
        $goods = $entry->getGoods();
        if ($goods) {
            $data['goods'] = Goods::format($goods, true, true);
        }
        $data['extra'] = $entry->getExtraData();
        $list[] = $data;
    }
}

$tpl_data['list'] = $list;

Response::showTemplate('web/inventory/log', $tpl_data);
