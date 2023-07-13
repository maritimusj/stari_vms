<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $qrcode = Advertising::get($id, Advertising::ACTIVE_QRCODE);
    if (empty($qrcode)) {
        Response::itoast('找不到这个活码！', $this->createWebUrl('qrcode'), 'error');
    }
    $qrcode->setState($qrcode->getState() == Advertising::NORMAL ? Advertising::BANNED : Advertising::NORMAL);
    if ($qrcode->save()) {
        Response::itoast('成功！', $this->createWebUrl('qrcode'), 'success');
    }
}

Response::itoast('失败！', $this->createWebUrl('qrcode'), 'error');