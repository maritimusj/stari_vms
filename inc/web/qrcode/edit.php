<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Advertising;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

$id = Request::int('id');
if ($id > 0) {
    $qrcode = Advertising::get($id, Advertising::ACTIVE_QRCODE);
    if (empty($qrcode)) {
        Response::toast('找不到这个活码！', We7::referer(), 'error');
    }

    $tpl_data['id'] = $id;
    $tpl_data['data'] = Advertising::format($qrcode);
}

Response::showTemplate('web/qrcode/edit', $tpl_data);