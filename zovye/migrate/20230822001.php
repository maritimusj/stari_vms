<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_order', 'transaction_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `transaction_id` VARCHAR(32) NULL AFTER `order_id`, ADD INDEX (`transaction_id`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_order', 'user_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `user_id` VARCHAR(32) NULL AFTER `goods_id`, ADD INDEX (`uniacid`, `user_id`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_order', 'account_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `account_id` VARCHAR(32) NULL AFTER `goods_id`, ADD INDEX (`uniacid`, `account_id`);
SQL;
    Migrate::execSQL($sql);
}