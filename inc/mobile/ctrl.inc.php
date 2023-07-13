<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Request::extraAjaxJsonData();

$op = 'default';
$data = [];

if (Request::has('op')) {
    $op = Request::op();
    $data = Request::array('data');
} elseif (Request::has('extra')) {
    $op = Request::str('event');
    $data = Request::array('extra');
}

$sign = Request::header('HTTP_ZOVYE_SIGN');
$no_str = Request::header('HTTP_ZOVYE_NOSTR');

$config = settings('ctrl', []);

//检查回调签名
if ($config['checkSign'] && CtrlServ::makeNotifierSign($config['appKey'], $config['appSecret'],
        $no_str,
        Request::raw()
    ) !== $sign) {
    Log::fatal('ctrl', [
        'error' => '签名检验失败！',
        'op' => $op,
        'sign' => $sign,
        'payload' => Request::raw(),
    ]);
}

DeviceEventProcessor::handle($op, $data);