<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data = [];

$id = request::int('id');
if ($id) {
    $qrcode = Advertising::get($id, Advertising::ACTIVE_QRCODE);
    if (empty($qrcode)) {
        Util::itoast('找不到这个活码！', We7::referer(), 'error');
    }

    $tpl_data['id'] = $id;
    $tpl_data['data'] = Advertising::format($qrcode);
}

app()->showTemplate('web/qrcode/edit', $tpl_data);