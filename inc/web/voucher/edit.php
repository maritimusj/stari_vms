<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$type = request::str('type');

$tpl_data = [
    'type' => $type,
];

if (request::has('id')) {
    $tpl_data['voucher_id'] = request::int('id');
}

app()->showTemplate("web/goods_voucher/edit", $tpl_data);