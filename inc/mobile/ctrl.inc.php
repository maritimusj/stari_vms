<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;

defined('IN_IA') or exit('Access Denied');

Request::extraAjaxJsonData();

$op = 'default';
$data = [];

if (Request::has('op')) {
    $op = Request::op();
    $data = Request::array('data');
    // 兼容原始设备消息模式
    if (empty($data['id'])) {
        $data['id'] = Request::str('id');
        if (Request::has('version')) {
            $data['version'] = Request::str('version');
        }
    }
} elseif (Request::has('event')) {
    $op = Request::str('event');
    $data = Request::array('extra');
    // 兼容原始设备消息模式
    if (!We7::starts_with($op, 'mcb.')) {
        $op = 'mcb.'.$op;
        $data = [
            'uid' => Request::str('uid'),
            'extra' => $data,
        ];
        if (Request::has('code')) {
            $data['code'] = Request::str('code');
        }
    }
}

Log::debug('ctrl', function () use ($op, $data) {
    return [
        'op' => $op,
        'data' => $data,
        'raw' => Request::raw(),
    ];
});

$config = settings('ctrl', []);

try {
    if ($config['checkSign'] && Request::header('HTTP_ZOVYE_SIGN') !== CtrlServ::makeNotifierSign(
            $config['appKey'],
            $config['appSecret'],
            Request::header('HTTP_ZOVYE_NOSTR'),
            Request::raw()
        )) {
        throw new RuntimeException('签名不正确！');
    }

    //消息处理
    DeviceEventProcessor::handle($op, $data);

} catch (Exception $e) {
    Log::error('events', [
        'event' => $op,
        'data' => $data,
        'error' => $e->getMessage(),
    ]);
}

Response::echo(CtrlServ::OK);