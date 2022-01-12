<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name . '_delivery')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_delivery` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `user_id` INT NOT NULL , 
    `phone_num` VARCHAR(32) NOT NULL , 
    `address` TEXT NOT NULL , 
    `status` INT NOT NULL , 
    `extra` JSON NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX `user` (`user_id`, `status`)
    ) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}
