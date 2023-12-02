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
    // 兼容原始设备消息模式
    if(empty($data['id'])) {
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

$sign = Request::header('HTTP_ZOVYE_SIGN');
$no_str = Request::header('HTTP_ZOVYE_NOSTR');

Log::debug('ctrl', function() use ($op, $data) {
    return [
        'op' => $op,
        'data' => $data,
        'raw' => Request::raw(),
    ];
});

$config = settings('ctrl', []);

//检查回调签名
if ($config['checkSign']) {
    $x = CtrlServ::makeNotifierSign($config['appKey'], $config['appSecret'],$no_str,Request::raw());
    if ($x !== $sign) {
        Log::fatal('ctrl', [
            'error' => '签名检验失败！',
            'op' => $op,
            'sign' => $sign,
            'raw' => Request::raw(),
        ]);
    }
}

DeviceEventProcessor::handle($op, $data);