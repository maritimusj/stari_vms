<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_table_exists(APP_NAME.'_goods_expire_alert')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_goods_expire_alert` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `agent_id` INT NOT NULL , 
    `device_id` INT NOT NULL , 
    `lane_id` INT NOT NULL , 
    `goods_id` INT NULL , 
    `expired_at` INT NOT NULL DEFAULT '0', 
    `extra` TEXT NULL , 
    `createtime` INT NULL , 
    PRIMARY KEY (`id`), 
    INDEX `agent` (`agent_d`), 
    INDEX `device` (`device_id`), 
    INDEX `goods` (`goods_id`), 
    ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}
