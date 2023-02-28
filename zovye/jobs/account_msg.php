<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\accountMsg;

//公众号消息推送

use zovye\CtrlServ;
use zovye\Log;
use zovye\Request;
use zovye\Wx;
use function zovye\request;

$media = [
    'type' => request('type'),
    'val' => request('val'),
    'delay' => request('delay'),
    'touser' => request('touser'),
];

$op = Request::op('default');

if ($op == 'account_msg' && CtrlServ::checkJobSign($media)) {
    $openid = $media['touser'];

    //推送领取消息
    if ($openid) {
        $msg = ['touser' => $openid];
        if ($media['type'] == 'text') {
            $msg['msgtype'] = 'text';
            $msg['text'] = [
                'content' => urlencode($media['val']),
            ];
        } elseif ($media['type'] == 'image') {
            $msg['msgtype'] = 'image';
            $msg['image'] = [
                'media_id' => $media['val'],
            ];
        } elseif ($media['type'] == 'mpnews') {
            $msg['msgtype'] = 'mpnews';
            $msg['mpnews'] = [
                'media_id' => $media['val'],
            ];
        }

        $res = Wx::sendCustomNotice($msg);
    }
}

Log::debug(
    'accountMsg',
    [
        'media' => $media,
        'result' => $res ?? '<null>',
    ]
);
