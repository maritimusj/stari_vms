<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_keeper_devices', 'commission_fixed')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keeper_devices` ADD `commission_fixed` int(11) NOT NULL DEFAULT -1 AFTER `keeper_id`;
SQL;
} else {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keeper_devices` MODIFY COLUMN `commission_fixed` int(11) NOT NULL DEFAULT -1;
ALTER TABLE `ims_zovye_vms_keeper_devices` MODIFY COLUMN `commission_percent` int(11) NOT NULL DEFAULT -1;
SQL;
}
Migrate::execSQL($sql);

if (!We7::pdo_field_exists($tb_name.'_keepers', 'extra')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keepers` ADD `extra` TEXT NULL AFTER `agentId`;
SQL;

    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_goods', 'sync')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods` ADD `sync` TINYINT NOT NULL DEFAULT '0' AFTER `price`;
SQL;

    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_keeper_devices', 'way')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keeper_devices` ADD `way` TINYINT NOT NULL DEFAULT '0' AFTER `kind`;
SQL;

    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_keeper_devices')) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ims_zovye_vms_keeper_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `keeper_id` int(11) NOT NULL,
  `commission_percent` int(11) NOT NULL DEFAULT '-1',
  `commission_fixed` int(11) NOT NULL DEFAULT '-1',
  `kind` tinyint(4) NOT NULL DEFAULT '0',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keeper` (`keeper_id`, `device_id`),
  KEY `device` (`device_id`, `keeper_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_referral')) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ims_zovye_vms_referral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL DEFAULT '0',
  `code` varchar(32) NOT NULL,
  `createtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_commission_balance')) {
    $sql = <<<SQL
RENAME TABLE ims_zovye_vms_commision_balance TO ims_zovye_vms_commission_balance;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_device_log')) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `deviceUID` varchar(64) NOT NULL,
  `event` tinyint(4) NOT NULL,
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `deviceUID` (`uniacid`,`deviceUID`(8),`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}


if (!We7::pdo_field_exists($tb_name.'_user', 'locked_uid')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_user` CHANGE `lockedGUID` `locked_uid` VARCHAR(64) NOT NULL DEFAULT 'n/a';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_field_exists($tb_name.'_device', 'locked_uid')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device` CHANGE `lockedGUID` `locked_uid` VARCHAR(64) NOT NULL DEFAULT 'n/a';
SQL;
    Migrate::execSQL($sql);
}

//排序值
if (!We7::pdo_field_exists($tb_name.'_device', 'rank')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device` ADD `rank` INT NULL AFTER `agentId`;
SQL;
    Migrate::execSQL($sql);
}

//设备分组表
if (!We7::pdo_table_exists($tb_name.'_device_groups')) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(64) NOT NULL DEFAULT '',
  `clr` varchar(32) NOT NULL DEFAULT '',
  `agentId` int(11) NOT NULL DEFAULT '0',
  `createtime` INT NOT NULL DEFAULT '0' , 
  PRIMARY KEY (`id`),
  KEY `agentId` (`uniacid`,`agentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}
if (!We7::pdo_table_exists($tb_name.'_goods_voucher')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_goods_voucher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `enable` tinyint(4) NOT NULL DEFAULT '0',
  `agentId` int(11) NOT NULL DEFAULT '0',
  `goodsId` int(11) NOT NULL,
  `total` int(11) NOT NULL DEFAULT '0',
  `extra` text NOT NULL,
  `used` int(11) NOT NULL DEFAULT '0',
  `begin` int(11) NOT NULL DEFAULT '0',
  `end` int(11) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_goods_voucher_logs')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_goods_voucher_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `code` varchar(16) NOT NULL,
  `ownerId` int(11) NOT NULL DEFAULT '0',
  `voucherId` int(11) NOT NULL DEFAULT '0',
  `goodsId` int(11) NOT NULL DEFAULT '0',
  `begin` int(11) NOT NULL DEFAULT '0',
  `end` int(11) NOT NULL DEFAULT '0',
  `usedtime` int(11) NOT NULL DEFAULT '0',
  `usedUserId` int(11) NOT NULL DEFAULT '0',
  `deviceId` int(11) NOT NULL DEFAULT '0',
  `createtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_device_feedback')) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `text` text NOT NULL,
  `pics` text NOT NULL,
  `deviceId` int(11) NOT NULL,
  `remark` varchar(200) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_table_exists($tb_name.'_data_vw')) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ims_zovye_vms_data_vw` (
`id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `k` varchar(60) NOT NULL,
  `v` varchar(120) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}



