<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\deviceModelObj;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'data') {
    $query = Device::query();
    
    if (Request::isset('online')) {
        $query->where(['mcb_online' => Request::bool('online') ? 1 : 0]);
    }
    if (Request::isset('error')) {
        if (Request::bool('error')) {
            $query->where(['error_code !=' => 0]);
        } else {
            $query->where(['error_code' => 0]);
        }
    }

    if (Request::isset('low')) {
        $query->where(['s2' => Request::bool('low') ? 1 : 0]);
    }

    $total = $query->count();

    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query->page($page, $page_size);

    $list = [];

    /** @var deviceModelObj $device */
    foreach($query->findAll() as $device) {
        $data = $device->profile();
        $data['location'] = $device->settings('extra.location.tencent', $device->settings('extra.location.baidu', []));

        $data['stats'] = [
            'month' => intval(Stats::getMonthTotal($device)['total']),
            'today' => intval(Stats::getDayTotal($device)['total']),
        ];

        $list[] = $data;
    }
    
    JSON::success([
        'list' => $list,
        'total' => $total,
        'totalpage' => ceil($total / $page_size),
    ]);

} elseif ($op == 'stats') {

    JSON::success(Stats::brief());
}

Response::showTemplate('misc/brief', [
    'api_url' => Util::murl('brief'),
]);