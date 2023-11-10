<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;
if (We7::pdo_table_exists($tb_name . '_prize')) {
    $sql = <<<SQL
DROP TABLE `ims_zovye_vms_aaf_balance`;
DROP TABLE `ims_zovye_vms_prize`;
DROP TABLE `ims_zovye_vms_prizelist`;
DROP TABLE `ims_zovye_vms_referal`;
SQL;
    Migrate::execSQL($sql);
}

if (We7::pdo_field_exists($tb_name . '_account', 'balance_deduct_num')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_account` DROP `balance_deduct_num`;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_balance', 'extra')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_balance` ADD INDEX(`src`);
ALTER TABLE `ims_zovye_vms_balance` ADD INDEX(`createtime`);
ALTER TABLE `ims_zovye_vms_balance` CHANGE `memo` `extra` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_balance_logs')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_balance_logs` (
    `id` INT NOT NULL AUTO_INCREMENT ,
    `user_id` INT NOT NULL , 
    `account_id` INT NOT NULL , 
    `extra` TEXT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), INDEX (`user_id`, `account_id`, `createtime`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_index_exists($tb_name.'_order', 'src')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_order` ADD INDEX(`uniacid`, `src`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_index_exists($tb_name.'_order', 'refund')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_order` ADD INDEX(`uniacid`, `refund`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_index_exists($tb_name.'_order', 'result_code')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_order` ADD INDEX(`uniacid`, `result_code`);
SQL;
    Migrate::execSQL($sql);
}