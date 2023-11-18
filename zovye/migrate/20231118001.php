<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_table_exists(APP_NAME.'_payment_config')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_payment_config` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `agent_id` INT NOT NULL DEFAULT '0', 
    `name` INT NOT NULL , 
    `extra` TEXT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`),
    UNIQUE (`uniacid`, `agent_id`, `name`)) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}