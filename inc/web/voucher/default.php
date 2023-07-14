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

if (empty($type)) {
    Response::showTemplate("web/goods_voucher/default", $tpl_data);
}

Response::showTemplate("web/goods_voucher/logs", $tpl_data);