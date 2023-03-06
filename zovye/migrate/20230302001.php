<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_gift')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_gift`(
    `id` INT NOT NULL AUTO_INCREMENT,
    `uniacid` INT NOT NULL,
    `agent_id` INT NOT NULL,
    `enabled` TINYINT(4) NOT NULL DEFAULT '0',
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `image` VARCHAR(255) NOT NULL DEFAULT '',
    `extra` JSON NOT NULL,
    `createtime` INT NOT NULL,
    PRIMARY KEY(`id`),
    INDEX(`uniacid`, `agent_id`, `enabled`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4; 

CREATE TABLE `ims_zovye_vms_gift_log`(
    `id` INT NOT NULL AUTO_INCREMENT,
    `uniacid` INT NOT NULL,
    `gift_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone_num` VARCHAR(20) NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `address` TEXT NOT NULL,
    `status` INT NOT NULL DEFAULT '0',
    `extra` JSON NOT NULL,
    `createtime` INT NOT NULL,
    PRIMARY KEY(`id`),
    INDEX(`uniacid`, `gift_id`),
    INDEX(`uniacid`, `user_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_tableexists($tb_name.'_lucky')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_lucky`(
    `id` INT NOT NULL AUTO_INCREMENT,
    `uniacid` INT NOT NULL,
    `agent_id` INT NOT NULL,
    `enabled` TINYINT(4) NOT NULL DEFAULT '0',
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `image` VARCHAR(255) NOT NULL DEFAULT '',
    `extra` JSON NOT NULL,
    `createtime` INT NOT NULL,
    PRIMARY KEY(`id`),
    INDEX(`uniacid`, `agent_id`, `enabled`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4; 

CREATE TABLE `ims_zovye_vms_lucky_log`(
    `id` INT NOT NULL AUTO_INCREMENT,
    `uniacid` INT NOT NULL,
    `lucky_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `serial` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone_num` VARCHAR(20) NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `address` TEXT NOT NULL,
    `status` INT NOT NULL DEFAULT '0',
    `extra` JSON NOT NULL,
    `createtime` INT NOT NULL,
    PRIMARY KEY(`id`),
    UNIQUE (`uniacid`, `serial`),
    INDEX(`uniacid`, `lucky_id`),
    INDEX(`uniacid`, `user_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}