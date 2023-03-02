<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_gift')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_gift`(
    `id` INT NOT NULL AUTO_INCREMENT,
    `uniacid` INT NOT NULL,
    `agent_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `extra` JSON NOT NULL,
    `createtime` INT NOT NULL,
    PRIMARY KEY(`id`),
    INDEX(`uniacid`, `agent_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4; 
CREATE TABLE `ims_zovye_vms_gift_log`(
    `id` INT NOT NULL AUTO_INCREMENT,
    `gift_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone_num` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `status` INT NOT NULL DEFAULT '0',
    `extra` JSON NOT NULL,
    `createtime` INT NOT NULL,
    PRIMARY KEY(`id`),
    INDEX(`gift_id`),
    INDEX(`user_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}