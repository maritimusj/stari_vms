<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::extraAjaxJsonData();

$op = 'default';
$data = [];

if (request::has('op')) {
    $op = request::op();
    $data = request::array('data');
} elseif (request::has('extra')) {
    $op = request::str('event');
    $data = request::array('extra');
}

$sign = request::header('HTTP_ZOVYE_SIGN');
$nostr = request::header('HTTP_ZOVYE_NOSTR');

//检查回调签名
if (settings('ctrl.checkSign') && CtrlServ::makeNotifierSign(
    settings('ctrl.appKey'), 
    settings('ctrl.appSecret'),
    $nostr,
    request::raw()
) !== $sign) {
    Util::logToFile('ctrl', [
        'error' => '签名检验失败！',
        'op' => $op,
        'sign' => $sign,
        'payload' => request::raw(),
    ]);
    exit();
}

DeviceEventProcessor::handle($op, $data);