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

if (empty($type)) {
    app()->showTemplate("web/goods_voucher/default", $tpl_data);
}

app()->showTemplate("web/goods_voucher/logs", $tpl_data);