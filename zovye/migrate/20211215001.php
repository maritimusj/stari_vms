<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_balance_logs', 's1')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_balance_logs` ADD `s1` INT NOT NULL DEFAULT '0' AFTER `account_id`;
ALTER TABLE `ims_zovye_vms_balance_logs` ADD INDEX(`s1`);
ALTER TABLE `ims_zovye_vms_balance_logs` ADD `s2` VARCHAR(64) NULL AFTER `s1`;
ALTER TABLE `ims_zovye_vms_balance_logs` ADD UNIQUE (`s2`);
SQL;
    Migrate::execSQL($sql);
}