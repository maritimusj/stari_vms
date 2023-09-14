<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\domain\Order;
use zovye\util\Util;

$params = [
    'agent_openid' => Request::str('agent_openid'),
    'account_id' => Request::int('account_id'),
    'device_id' => Request::int('device_id'),
    'start' => Request::str('start'),
    'end' => Request::str('end'),
];

$query = Order::getExportQuery($params);

if (is_error($query)) {
    JSON::fail($query);
}

$step = Request::str('step');
if (empty($step) || $step == 'init') {
    $params['headers'] = Request::array('headers');
    $params['op'] = 'export_do';
    Response::templateJSON(
        'web/common/export',
        '导出订单',
        [
            'api_url' => Util::url('order', $params),
            'total' => $query->count(),
            'serial' => (new DateTime())->format('YmdHis'),
        ]
    );
} else {
    $serial = Request::str('serial');
    if (empty($serial)) {
        JSON::fail("缺少serial");
    }

    $filename = "$serial.csv";
    $dirname = "export/order/";
    $full_filename = Helper::getAttachmentFileName($dirname, $filename);

    if ($step == 'load') {
        $query = $query->where(['id >' => Request::int('last')])->limit(100)->orderBy('id ASC');
        $last_id = Order::export($full_filename, $query, Request::array('headers'));

        JSON::success([
            'num' => 100,
            'last' => $last_id,
        ]);

    } elseif ($step == 'download') {
        JSON::success([
            'url' => Util::toMedia("$dirname$filename"),
        ]);
    }
}

JSON::fail('不正确的请求！');