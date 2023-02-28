<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

//分配设备控件查询标签详情
use zovye\model\tagsModelObj;

if (Request::is_ajax() && Request::has('id')) {
    /** @var tagsModelObj $res */
    $res = m('tags')->findOne(We7::uniacid(['id' => Request::int('id')]));
    if ($res) {
        $tag = [
            'id' => $res->getId(),
            'title' => strval($res->getTitle()),
            'count' => intval($res->getCount()),
        ];
        JSON::success($tag);
    }

    JSON::fail('找不到这个标签');
}

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = m('tags')->where(We7::uniacid([]));

//搜索指定ID
$ids = Util::parseIdsFromGPC();
if (!empty($ids)) {
    $query->where(['id' => $ids]);
}

//搜索关键字
$keywords = Request::trim('keywords');
if ($keywords) {
    $query->where(['title LIKE' => "%$keywords%"]);
}

$total = $query->count();
$tags = [
    'page' => 0,
    'total' => 0,
    'totalpage' => 0,
    'list' => [],
];

if ($total > 0) {
    $total_page = ceil($total / $page_size);

    $tags['total'] = $total;
    $tags['page'] = $page;
    $tags['totalpage'] = $total_page;

    $query->page($page, $page_size);
    $query->orderBy('id ASC');

    /** @var  tagsModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $tag = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'count' => $entry->getCount(),
        ];
        $tags['list'][] = $tag;
    }
}

if (Request::is_ajax()) {
    $tags['serial'] = request('serial') ?: microtime(true).'';
    JSON::success($tags);
}

app()->showTemplate('web/tags/default', [
    'tags' => $tags,
]);