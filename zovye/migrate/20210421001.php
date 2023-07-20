<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_fieldexists($tb_name.'_device', 's1')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device` ADD `s3` TINYINT(1) NOT NULL DEFAULT '0' AFTER `shadow_id`, ADD INDEX (`s3`);
ALTER TABLE `ims_zovye_vms_device` ADD `s2` TINYINT(1) NOT NULL DEFAULT '0' AFTER `shadow_id`, ADD INDEX (`s2`);
ALTER TABLE `ims_zovye_vms_device` ADD `s1` TINYINT(1) NOT NULL DEFAULT '0' AFTER `shadow_id`, ADD INDEX (`s1`);
SQL;
    Migrate::execSQL($sql);
}
