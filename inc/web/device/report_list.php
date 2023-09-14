<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Device;
use zovye\domain\Maintenance;

defined('IN_IA') or exit('Access Denied');

//设备故障 提交列表

$tpl_data = [];

$query = Maintenance::query();

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$total = $query->count();

//列表数据
$data = [];

$condition = We7::uniacid([]);

$query = We7::load()->object('query');

$join = $query
    ->from(Maintenance::model()->getTableName(), 'm')
    ->leftjoin(Device::getTableName(), 'd')
    ->on('m.device_id', 'd.imei');

//搜索关键字
$keywords = Request::trim('keywords');
if ($keywords) {
    $join->whereor("d.name LIKE", "%$keywords%")
        ->whereor("d.imei LIKE", "%$keywords%")
        ->whereor("d.app_id LIKE", "%$keywords%")
        ->whereor("d.iccid LIKE", "%$keywords%");
}

$res = $join
    ->where($condition)
    ->page($page, $page_size)
    ->select('m.*', 'm.name as mname', 'd.name as dname', 'd.imei')
    ->orderby("m.createtime DESC")
    ->getAll();

foreach ($res as $entry) {
    $data[] = [
        'id' => $entry['id'],
        'mobile' => $entry['mobile'],
        'mname' => $entry['mname'],
        'result' => $entry['result'],
        'createtime_formatted' => date('Y-m-d H:i', $entry['createtime']),
        'dname' => is_null($entry['dname']) ? '' : $entry['dname'],
        'imei' => $entry['imei'],
    ];
}

$filter = [
    'page' => $page,
    'pagesize' => $page_size,
];

if ($keywords) {
    $filter['keywords'] = $keywords;
}

$tpl_data['data'] = $data;
$tpl_data['filter'] = $filter;
$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

Response::showTemplate('web/device/report_list', $tpl_data);