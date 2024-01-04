<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_field_exists(APP_NAME.'_keeper_devices', 'commission_free_fixed')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keeper_devices` 
ADD `commission_free_fixed` INT NOT NULL DEFAULT '-1' AFTER `commission_fixed`, 
ADD `commission_free_percent` INT NOT NULL DEFAULT '-1' AFTER `commission_free_fixed`;
SQL;
    Migrate::execSQL($sql);
}
