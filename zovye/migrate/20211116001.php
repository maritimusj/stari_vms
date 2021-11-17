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

if (!We7::pdo_fieldexists($tb_name . '_balance', 'extra')) {
$sql = <<<SQL
ALTER TABLE `ims_zovye_vms_balance` ADD INDEX(`src`);
ALTER TABLE `ims_zovye_vms_balance` ADD INDEX(`createtime`);
ALTER TABLE `ims_zovye_vms_balance` CHANGE `memo` `extra` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}
