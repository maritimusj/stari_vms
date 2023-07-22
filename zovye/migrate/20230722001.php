<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_cron')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_device_cron` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `uid` VARCHAR(64) NOT NULL , 
    `spec` VARCHAR(32) NOT NULL , 
    `url` VARCHAR NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`uniacid`), INDEX (`uid`), INDEX (`uniacid`,`uid`)) 
    ENGINE = InnoDB;

SQL;
    Migrate::execSQL($sql);
}
