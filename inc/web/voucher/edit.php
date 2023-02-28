<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$type = Request::str('type');

$tpl_data = [
    'type' => $type,
];

if (Request::has('id')) {
    $tpl_data['voucher_id'] = Request::int('id');
}

app()->showTemplate("web/goods_voucher/edit", $tpl_data);