<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_table_exists(APP_NAME.'_goods_expire_alert')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_goods_expire_alert` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `agent_id` INT NOT NULL DEFAULT '0', 
    `device_id` INT NOT NULL , 
    `lane_id` INT NOT NULL , 
    `expired_at` INT NOT NULL DEFAULT '0', 
    `pre_days` INT NOT NULL DEFAULT '0', 
    `invalid_if_expired` INT NOT NULL DEFAULT '0', 
    `createtime` INT NULL , 
    PRIMARY KEY (`id`), 
    INDEX `agent` (`agent_id`), 
    INDEX `device` (`device_id`), 
    ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}
