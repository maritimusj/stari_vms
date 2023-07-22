<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_fieldexists($tb_name.'_device_groups', 'extra')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_device_groups` ADD `type_id` INT NOT NULL DEFAULT '0' AFTER `uniacid`, ADD INDEX (`type_id`);
    ALTER TABLE `ims_zovye_vms_device_groups` ADD `extra` TEXT AFTER `agent_id`;
    ALTER TABLE `ims_zovye_vms_device_groups` ADD `loc` POINT NULL AFTER `agent_id`;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_user', 's1')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_user` ADD `s1` VARCHAR(32) NULL AFTER `mobile`, ADD INDEX (`s1`);
SQL;
    Migrate::execSQL($sql);
}

//暂时取消，需要时手动升级
//    $sql = <<<SQL
//ALTER TABLE `ims_zovye_vms_order` CHANGE `order_id` `order_id` VARCHAR(64) NOT NULL;
//SQL;
//    Migrate::execSQL($sql);