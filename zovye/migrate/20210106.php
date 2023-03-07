<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_fieldexists($tb_name.'_user', 'superior_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_user` CHANGE `superiorId` `superior_id` INT(11) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}


if (!We7::pdo_fieldexists($tb_name.'_account', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_account` CHANGE `agentId` `agent_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_account` CHANGE `orderlimits` `order_limits` INT(11) NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_account` CHANGE `orderno` `order_no` INT(11) NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_account` CHANGE `groupname` `group_name` VARCHAR(255) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_advertising', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_advertising` CHANGE `agentId` `agent_id` INT(11) NULL DEFAULT '0';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_advs_stats', 'advs_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_advs_stats` CHANGE `advsId` `advs_id` INT(11) NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_advs_stats` CHANGE `deviceId` `device_id` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_advs_stats` CHANGE `accountId` `account_id` VARCHAR(64) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_agent_msg', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_agent_msg` CHANGE `agentId` `agent_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_agent_msg` CHANGE `msgId` `msg_id` INT(11) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_commission_balance', 'x_val')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_commission_balance` CHANGE `xval` `x_val` INT(11) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_coupon', 'x_val')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_coupon` CHANGE `xval` `x_val` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_coupon` CHANGE `xrequire` `x_require` INT(11) NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_coupon` CHANGE `usedtime` `used_time` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_coupon` CHANGE `expiredtime` `expired_time` INT(11) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_device_events', 'device_uid')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device_events` CHANGE `deviceUID` `device_uid` VARCHAR(64) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_device_feedback', 'user_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device_feedback` CHANGE `userId` `user_id` INT(11) NOT NULL;
ALTER TABLE `ims_zovye_vms_device_feedback` CHANGE `deviceId` `device_id` INT(11) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_device_groups', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device_groups` CHANGE `agentId` `agent_id` INT(11) NOT NULL DEFAULT '0';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_device_record', 'device_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device_record` CHANGE `deviceId` `device_id` INT(11) NOT NULL;
ALTER TABLE `ims_zovye_vms_device_record` CHANGE `userId` `user_id` INT(11) NOT NULL;
ALTER TABLE `ims_zovye_vms_device_record` CHANGE `agentId` `agent_id` INT(11) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_device_types', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device_types` CHANGE `agentId` `agent_id` INT(11) NOT NULL;
ALTER TABLE `ims_zovye_vms_device_types` CHANGE `deviceId` `device_id` INT(11) NOT NULL DEFAULT '0';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_device', 'group_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device` CHANGE `groupId` `group_id` INT(11) NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_device` CHANGE `lastOnline` `last_online` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `lastPing` `last_ping` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `appId` `app_id` VARCHAR(128) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `appLastOnline` `app_last_online` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `appVersion` `app_version` VARCHAR(10) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `agentId` `agent_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `tagsData` `tags_data` VARCHAR(512) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `shadowId` `shadow_id` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_device` CHANGE `errorCode` `error_code` INT(11) NULL DEFAULT '0';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_goods_voucher_logs', 'owner_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods_voucher_logs` CHANGE `ownerId` `owner_id` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_goods_voucher_logs` CHANGE `voucherId` `voucher_id` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_goods_voucher_logs` CHANGE `goodsId` `goods_id` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_goods_voucher_logs` CHANGE `usedtime` `used_time` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_goods_voucher_logs` CHANGE `usedUserId` `used_user_id` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_goods_voucher_logs` CHANGE `deviceId` `device_id` INT(11) NOT NULL DEFAULT '0';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_goods_voucher', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods_voucher` CHANGE `agentId` `agent_id` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `ims_zovye_vms_goods_voucher` CHANGE `goodsId` `goods_id` INT(11) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_goods', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods` CHANGE `agentId` `agent_id` INT(11) NOT NULL DEFAULT '0';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_keepers', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keepers` CHANGE `agentId` `agent_id` INT(11) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_login_data', 'user_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_login_data` CHANGE `userid` `user_id` INT(11) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_tableexists($tb_name.'_maintenance')) {
    $sql = <<<SQL
RENAME TABLE `ims_zovye_vms_maintance` TO `ims_zovye_vms_maintenance`
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_maintenance', 'device_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_maintenance` CHANGE `deviceId` `device_id` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_maintenance` CHANGE `errorCode` `error_code` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_maintenance` CHANGE `resultCode` `result_code` INT(11) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_order', 'order_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` CHANGE `orderId` `order_id` VARCHAR(32) NOT NULL;
ALTER TABLE `ims_zovye_vms_order` CHANGE `agentId` `agent_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_order` CHANGE `deviceId` `device_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `ims_zovye_vms_order` CHANGE `goodsId` `goods_id` INT(11) NULL DEFAULT NULL;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_order', 'result_code')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_order` ADD `result_code` INT NULL DEFAULT '0' AFTER `extra`;
ALTER TABLE `ims_zovye_vms_order` ADD `refund` INT NULL DEFAULT '0' AFTER `result_code`;
SQL;
    Migrate::execSQL($sql);
}


if (!We7::pdo_fieldexists($tb_name.'_replenish', 'device_uid')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_replenish` CHANGE `deviceUID` `device_uid` VARCHAR(64) NOT NULL;
ALTER TABLE `ims_zovye_vms_replenish` CHANGE `agentId` `agent_id` INT(11) NOT NULL;
ALTER TABLE `ims_zovye_vms_replenish` CHANGE `keeperId` `keeper_id` INT(11) NOT NULL;
ALTER TABLE `ims_zovye_vms_replenish` CHANGE `goodsId` `goods_id` INT(11) NOT NULL DEFAULT '0';
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_settings_user', 'lock_uid')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_settings_user` CHANGE `__lockedGUID` `lock_uid` VARCHAR(32) NULL DEFAULT 'n/a';
SQL;
    Migrate::execSQL($sql);
}