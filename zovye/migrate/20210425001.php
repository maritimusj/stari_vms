<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_table_exists($tb_name.'_wx_app')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_wx_app` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `name` VARCHAR(128) NOT NULL , 
    `key` VARCHAR(64) NOT NULL , 
    `secret` VARCHAR(64) NOT NULL , 
    `createtime` INT NOT NULL , PRIMARY KEY (`id`), UNIQUE `key` (`uniacid`, `key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}