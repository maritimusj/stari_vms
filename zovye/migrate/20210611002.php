<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_table_exists($tb_name.'_component_user')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_component_user` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `user_id` INT NOT NULL,
    `appid` VARCHAR(64) NOT NULL , 
    `openid` VARCHAR(128) NOT NULL , 
    `extra` TEXT , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`uniacid`,`user_id`),
    INDEX (`uniacid`,`appid`),
    INDEX (`uniacid`,`openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);

}
