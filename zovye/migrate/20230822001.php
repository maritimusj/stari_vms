<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_order', 'transaction_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `transaction_id` INT NULL AFTER `order_id`, ADD INDEX (`transaction_id`);
SQL;
    Migrate::execSQL($sql);
}
