<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
if ($id) {
    if (Advertising::remove($id, Advertising::ACTIVE_QRCODE)) {
        Util::itoast('删除成功！', $this->createWebUrl('qrcode'), 'success');
    }
}

Util::itoast('删除失败！', $this->createWebUrl('qrcode'), 'error');