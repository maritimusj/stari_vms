<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_field_exists(APP_NAME.'_keeper_devices', 'device_qoe_bonus_percent')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keeper_devices` 
ADD `device_qoe_bonus_percent` INT NOT NULL DEFAULT '0' AFTER `commission_fixed`, 
ADD `app_online_bonus_percent` INT NOT NULL DEFAULT '0' AFTER `device_qoe_bonus_percent`;
SQL;
    Migrate::execSQL($sql);
}
