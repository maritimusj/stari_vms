<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;

$params = [
    'agent_openid' => request::str('agent_openid'),
    'account_id' => request::int('account_id'),
    'device_id' => request::int('device_id'),
    'start' => request::str('start'),
    'end' => request::str('end'),
];

$query = Order::getExportQuery($params);

if (is_error($query)) {
    JSON::fail($query);
}

$step = request::str('step');
if (empty($step) || $step == 'init') {
    $params['headers'] = request::array('headers');
    $params['op'] = 'export_do';
    $content = app()->fetchTemplate('web/common/export', [
        'api_url' => Util::url('order', $params),
        'total' => $query->count(),
        'serial' => (new DateTime())->format('YmdHis'),
    ]);

    JSON::success([
        'title' => "导出订单",
        'content' => $content,
    ]);
} else {
    $serial = request::str('serial');
    if (empty($serial)) {
        JSON::fail("缺少serial");
    }

    $filename = "$serial.csv";
    $dirname = "export/order/";
    $full_filename = Util::getAttachmentFileName($dirname, $filename);

    if ($step == 'load') {
        $query = $query->where(['id >' => request::int('last')])->limit(100)->orderBy('id asc');
        $last_id = Order::export($full_filename, $query, request::array('headers'));

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