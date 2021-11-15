<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name . '_account_query')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_account_query` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `request_id` VARCHAR(64) NOT NULL ,
    `account_id` INT NOT NULL , 
    `device_id` INT NOT NULL , 
    `user_id` INT NOT NULL , 
    `request` JSON NULL , 
    `result` JSON NULL , 
    `extra` TEXT , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`),
    INDEX (`request_id`), 
    INDEX (`account_id`),
    INDEX (`device_id`),
    INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}