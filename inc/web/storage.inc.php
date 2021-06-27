<?php
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $query = Storage::query();

    //搜索关键字
    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->whereOr([
            'title LIKE' => "%{$keywords}%",
        ]);
    }

    $total = $query->count();
    $storages = [
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

        $storages['total'] = $total;
        $storages['page'] = $page;
        $storages['totalpage'] = $total_page;

        $query->orderBy('id DESC');
        foreach($query->findAll() as $entry) {
            $storages['list'][] = $entry->format();
        }
    }

    app()->showTemplate('web/storage/default', [
        'op' => $op,
        'storages' => $storages,
    ]);

} elseif ($op == 'search') {
    $query = Storage::query();
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
        $storage = Storage::get(request::int('id'));
        if (empty($storage)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $tpl_data['storage'] = $storage;
    }

    app()->showTemplate('web/storage/edit', $tpl_data);

} elseif ($op == 'save') {

    $id = request::int('id');
    if ($id > 0) {
        $storage = Storage::get($id);
        if (empty($storage)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $storage->setTitle(request::trim('title'));
        $storage->save();
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
        $uid = Storage::getUID($user);
        if (Storage::exists($uid)) {
            Util::itoast('仓库已经存在！', '', 'error');
        }
        $data['uid'] = $uid;
        $data['extra'] = [
            'user' => $user->profile(),
        ];
    }

    $parent_storage_id = request::int('parentStorageId');
    if ($parent_storage_id > 0) {
        $parent_storage = Storage::get($parent_storage_id);
        if (empty($parent_storage)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $data['parent_id'] = $parent_storage->getId();
    }

    $storage = Storage::create($data);
    if ($storage) {
        Util::itoast('创建成功！', '', 'success');
    }

    Util::itoast('创建失败！', '', 'error');

} elseif ($op == 'getUserStorage') {

    $user_id = request::int('user_id');
    $user = User::get($user_id);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }
    $storage = Storage::find($user, request::trim('name'));
    if (empty($storage)) {
        JSON::fail('找不到指定的仓库！');
    }
    JSON::success([
        'id' => $storage->getId(),
        'uid' => $storage->getUid(),
        'title' => $storage->getTitle(),
        'createtime' => $storage->getCreatetime(),
        'createtime_formatted' => date('Y-m-d H:i:s', $storage->getCreatetime()),
    ]);

} elseif ($op == 'detail') {

    $storage = Storage::get(request::int('id'));
    if (empty($storage)) {
        Util::itoast('找不到这个仓库！', '', 'error');
    }

    $tpl_data = [
        'title' => $storage->getTitle(),
    ];
    app()->showTemplate('web/storage/detail', $tpl_data);
}
