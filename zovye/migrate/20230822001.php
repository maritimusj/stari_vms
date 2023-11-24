<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_order', 'transaction_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `transaction_id` VARCHAR(32) NULL AFTER `order_id`, ADD INDEX t1 (`uniacid`, `transaction_id`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_order', 'user_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `user_id` INT NULL AFTER `goods_id`, ADD INDEX u1 (`uniacid`, `user_id`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_order', 'account_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `account_id` INT NULL AFTER `goods_id`, ADD INDEX a1 (`uniacid`, `account_id`);
SQL;
    Migrate::execSQL($sql);
}

//升级完成标志
updateSettings('migration.order', '20230822');