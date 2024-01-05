<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_field_exists(APP_NAME.'_keeper_devices', 'commission_free_fixed')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keeper_devices` 
ADD `commission_free_fixed` INT NOT NULL DEFAULT '-1' AFTER `commission_fixed`, 
ADD `commission_free_percent` INT NOT NULL DEFAULT '-1' AFTER `commission_free_fixed`;

CREATE OR REPLACE VIEW `ims_zovye_vms_device_keeper_vw` AS 
SELECT d.*,IFNULL(k.keeper_id,0) keeper_id,k.commission_percent, k.commission_fixed,k.commission_free_percent, k.commission_free_fixed,IFNULL(k.kind,0) kind,IFNULL(k.way,0) way FROM `ims_zovye_vms_device` d 
LEFT JOIN `ims_zovye_vms_keeper_devices` k ON d.id=k.device_id WHERE 1;

UPDATE `ims_zovye_vms_keeper_devices`  set `commission_free_fixed`=`commission_fixed` WHERE `commission_fixed` > 0;
SQL;
    Migrate::execSQL($sql);
}
