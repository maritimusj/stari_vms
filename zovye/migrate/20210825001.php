<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_cache')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_cache` (
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uid` VARCHAR(64) NOT NULL , 
    `data` TEXT NOT NULL , 
    `createtime` INT NOT NULL , 
    `expiretime` INT NOT NULL , 
    `updatetime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    UNIQUE (`uid`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}