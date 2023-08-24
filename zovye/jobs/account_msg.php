<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\accountMsg;

defined('IN_IA') or exit('Access Denied');

//公众号消息推送

use zovye\CtrlServ;
use zovye\JobException;
use zovye\Log;
use zovye\Wx;
use function zovye\request;

$log = [
    'type' => request('type'),
    'val' => request('val'),
    'delay' => request('delay'),
    'touser' => request('touser'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$openid = $log['touser'];

//推送领取消息
if ($openid) {
    $msg = ['touser' => $openid];
    if ($log['type'] == 'text') {
        $msg['msgtype'] = 'text';
        $msg['text'] = [
            'content' => urlencode($log['val']),
        ];
    } elseif ($log['type'] == 'image') {
        $msg['msgtype'] = 'image';
        $msg['image'] = [
            'media_id' => $log['val'],
        ];
    } elseif ($log['type'] == 'mpnews') {
        $msg['msgtype'] = 'mpnews';
        $msg['mpnews'] = [
            'media_id' => $log['val'],
        ];
    }

    $res = Wx::sendCustomNotice($msg);
}

Log::debug(
    'accountMsg',
    [
        'media' => $log,
        'result' => $res ?? '<null>',
    ]
);
