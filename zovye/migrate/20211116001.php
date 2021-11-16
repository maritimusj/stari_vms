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