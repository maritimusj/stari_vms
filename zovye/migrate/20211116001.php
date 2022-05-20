<?php

namespace zovye;

$tb_name = APP_NAME;

$sql = <<<SQL
ALTER TABLE `ims_zovye_vms_account` DROP `balance_deduct_num`;
DROP TABLE `ims_zovye_vms_aaf_balance`;
DROP TABLE `ims_zovye_vms_prize`;
DROP TABLE `ims_zovye_vms_prizelist`;
DROP TABLE `ims_zovye_vms_referal`;
SQL;
Migrate::execSQL($sql);

if (!We7::pdo_fieldexists($tb_name.'_balance', 'extra')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_balance` ADD INDEX(`src`);
ALTER TABLE `ims_zovye_vms_balance` ADD INDEX(`createtime`);
ALTER TABLE `ims_zovye_vms_balance` CHANGE `memo` `extra` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_tableexists($tb_name.'_balance_logs')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_balance_logs` (
    `id` INT NOT NULL AUTO_INCREMENT ,
    `user_id` INT NOT NULL , 
    `account_id` INT NOT NULL , 
    `extra` TEXT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), INDEX (`user_id`, `account_id`, `createtime`)
) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_indexexists($tb_name.'_order', 'src')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_order` ADD INDEX(`src`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_indexexists($tb_name.'_order', 'refund')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_order` ADD INDEX(`refund`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_indexexists($tb_name.'_order', 'result_code')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_order` ADD INDEX(`result_code`);
SQL;
    Migrate::execSQL($sql);
}