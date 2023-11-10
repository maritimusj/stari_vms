<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_inventory_log', 'src_inventory_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_inventory_log` ADD `src_inventory_id` INT NOT NULL DEFAULT '0' AFTER `id`;
SQL;
    Migrate::execSQL($sql);
}


if (!We7::pdo_field_exists($tb_name.'_locker', 'used')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_locker` ADD `used` INT NOT NULL DEFAULT '0' AFTER `available`;
SQL;
    Migrate::execSQL($sql);

    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_locker` DROP INDEX `request_id`, ADD INDEX `request_id` (`request_id`) USING BTREE;
SQL;
    Migrate::execSQL($sql);
}