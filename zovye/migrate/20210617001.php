<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_table_exists($tb_name.'_locker')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_locker` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uid` VARCHAR(64) NOT NULL , 
    `request_id` VARCHAR(64) NOT NULL DEFAULT '', 
    `expired_at` INT NOT NULL ,     
    `available` INT NOT NULL DEFAULT '1' , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    UNIQUE (`uid`), 
    INDEX (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}
