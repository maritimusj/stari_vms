<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_fieldexists($tb_name . '_balance_logs', 's1')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_balance_logs` ADD `s1` INT NOT NULL DEFAULT '0' AFTER `account_id`;
ALTER TABLE `ims_zovye_vms_balance_logs` ADD INDEX(`s1`);
ALTER TABLE `ims_zovye_vms_balance_logs` ADD `s2` VARCHAR(64) NULL AFTER `s1`;
ALTER TABLE `ims_zovye_vms_balance_logs` ADD UNIQUE (`s2`);

CREATE OR REPLACE VIEW `ims_zovye_vms_task_view` AS 
SELECT log.*,acc.state FROM `ims_zovye_vms_balance_logs` AS log INNER JOIN `ims_zovye_vms_account` as acc ON log.account_id=acc.id
WHERE type=110;
SQL;
    Migrate::execSQL($sql);
}