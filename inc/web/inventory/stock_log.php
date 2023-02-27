<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\inventory_logModelObj;

$user = User::get(request::int('id'));
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

if (request::isset('src')) {
    $query->where(['src_inventory_id' => request::int('src')]);
}

if (request::has('goods')) {
    $query->where(['goods_id' => request::int('goods')]);
}

$total = $query->count();
$list = [];

if ($total > 0) {
    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

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

app()->showTemplate('web/inventory/log', $tpl_data);
