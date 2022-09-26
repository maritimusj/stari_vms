<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_team')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_team` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `owner_id` INT NOT NULL , 
    `name` VARCHAR(32) NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`uniacid`, `owner_id`)) ENGINE = InnoDB;
CREATE TABLE `ims_zovye_vms_team_member` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `team_id` INT NOT NULL , 
    `user_id` INT NOT NULL DEFAULT '0', 
    `mobile` VARCHAR(32) NOT NULL DEFAULT '', 
    `name` VARCHAR(32) NOT NULL , 
    `remark` VARCHAR(128) NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`team_id`, `user_id`), 
    INDEX (`mobile`)) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}