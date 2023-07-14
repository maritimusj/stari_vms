<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$type = Request::str('type');

$tpl_data = [
    'type' => $type,
];

if (Request::has('id')) {
    $tpl_data['voucher_id'] = Request::int('id');
}

Response::showTemplate("web/goods_voucher/edit", $tpl_data);