<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
if ($id) {
    $qrcode = Advertising::get($id, Advertising::ACTIVE_QRCODE);
    if (empty($qrcode)) {
        Util::itoast('找不到这个活码！', $this->createWebUrl('qrcode'), 'error');
    }
    $qrcode->setState($qrcode->getState() == Advertising::NORMAL ? Advertising::BANNED : Advertising::NORMAL);
    if ($qrcode->save()) {
        Util::itoast('成功！', $this->createWebUrl('qrcode'), 'success');
    }
}

Util::itoast('失败！', $this->createWebUrl('qrcode'), 'error');