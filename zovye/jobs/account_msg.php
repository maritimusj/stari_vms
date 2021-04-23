<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\accountMsg;

//公众号消息推送

use zovye\CtrlServ;
use zovye\request;
use zovye\Util;
use zovye\Wx;
use function zovye\request;

$media = [
    'type' => request('type'),
    'val' => request('val'),
    'delay' => request('delay'),
    'touser' => request('touser'),
];

$op = request::op('default');

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

Util::logToFile(
    'accountMsg',
    [
        'media' => $media,
        'result' => isset($res) ? $res : '<null>',
    ]
);
