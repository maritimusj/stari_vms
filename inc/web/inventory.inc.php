<?php
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $query = Inventory::query();

    //搜索关键字
    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->whereOr([
            'title LIKE' => "%{$keywords}%",
        ]);
    }

    $total = $query->count();
    $inventories = [
        'page' => 0,
        'total' => 0,
        'totalpage' => 0,
        'list' => [],
    ];

    if ($total > 0) {
        $total_page = ceil($total / $page_size);
        if ($page > $total_page) {
            $page = 1;
        }

        $inventories['total'] = $total;
        $inventories['page'] = $page;
        $inventories['totalpage'] = $total_page;

        $query->orderBy('id DESC');
        foreach($query->findAll() as $entry) {
            $inventories['list'][] = $entry->format();
        }
    }

    app()->showTemplate('web/inventory/default', [
        'op' => $op,
        'inventories' => $inventories,
    ]);

} elseif ($op == 'search') {
    $query = Inventory::query();
    //搜索关键字
    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->whereOr([
            'title LIKE' => "%{$keywords}%",
        ]);
    }

    $query->limit(100)->orderBy('id DESC');
    $result = [];
    foreach($query->findAll() as $entry) {
        $result[] = $entry->format();
    }

    JSON::success($result);

} elseif ($op == 'add' || $op == 'edit') {

    $tpl_data = [
        'op' => $op,
    ];

    if ($op == 'edit') {
        $inventory = Inventory::get(request::int('id'));
        if (empty($inventory)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $tpl_data['inventory'] = $inventory;
    }

    app()->showTemplate('web/inventory/edit', $tpl_data);

} elseif ($op == 'save') {

    $id = request::int('id');
    if ($id > 0) {
        $inventory = Inventory::get($id);
        if (empty($inventory)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $inventory->setTitle(request::trim('title'));
        $inventory->save();
        Util::itoast('保存成功！', '', 'success');
    }

    $data = [];
    $data['title'] = request::trim('title');

    $user_id = request::int('userId');
    if ($user_id > 0) {
        $user = User::get($user_id);
        if (empty($user)) {
            Util::itoast('找不到这个用户！', '', 'error');
        }
        $uid = Inventory::getUID($user);
        if (Inventory::exists($uid)) {
            Util::itoast('仓库已经存在！', '', 'error');
        }
        $data['uid'] = $uid;
        $data['extra'] = [
            'user' => $user->profile(),
        ];
    }

    $parent_inventory_id = request::int('parentId');
    if ($parent_inventory_id > 0) {
        $parent_inventory = Inventory::get($parent_inventory_id);
        if (empty($parent_inventory)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $data['parent_id'] = $parent_inventory->getId();
    }

    $inventory = Inventory::create($data);
    if ($inventory) {
        Util::itoast('创建成功！', '', 'success');
    }

    Util::itoast('创建失败！', '', 'error');

} elseif ($op == 'getUserInventory') {

    $user_id = request::int('user_id');
    $user = User::get($user_id);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }
    $inventory = Inventory::find($user, request::trim('name'));
    if (empty($inventory)) {
        JSON::fail('找不到指定的仓库！');
    }
    JSON::success([
        'id' => $inventory->getId(),
        'uid' => $inventory->getUid(),
        'title' => $inventory->getTitle(),
        'createtime' => $inventory->getCreatetime(),
        'createtime_formatted' => date('Y-m-d H:i:s', $inventory->getCreatetime()),
    ]);

} elseif ($op == 'detail') {

    $inventory = Inventory::get(request::int('id'));
    if (empty($inventory)) {
        Util::itoast('找不到这个仓库！', '', 'error');
    }

    $tpl_data = [
        'title' => $inventory->getTitle(),
    ];
    app()->showTemplate('web/inventory/detail', $tpl_data);
}
