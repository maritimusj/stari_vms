<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_cron')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_cron` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `uid` VARCHAR(64) NOT NULL , 
    `job_uid` VARCHAR(64) NOT NULL , 
    `spec` VARCHAR(32) NOT NULL , 
    `url` VARCHAR(255) NOT NULL , 
    `extra` TEXT,
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`uniacid`), 
    INDEX (`uniacid`,`uid`),
    INDEX (`uniacid`,`job_uid`)) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}
