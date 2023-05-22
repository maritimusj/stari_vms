<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_charging_now_data')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_charging_now_data` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `serial` VARCHAR(64) NOT NULL , 
    `user_id` INT NOT NULL , 
    `device_id` INT NOT NULL , 
    `charger_id` INT NOT NULL , 
    `extra` TEXT ,
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`user_id`), 
    INDEX (`device_id`), 
    UNIQUE (`serial`)) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}