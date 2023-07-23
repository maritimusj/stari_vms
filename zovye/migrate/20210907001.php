<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_table_exists($tb_name.'_counter')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_counter` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uid` VARCHAR(64) NOT NULL , 
    `num` INT NOT NULL DEFAULT '0' , 
    `createtime` INT NOT NULL , 
    `updatetime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    UNIQUE (`uid`)
) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}