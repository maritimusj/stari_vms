<?php
namespace zovye;

$tb_name = 'zovye_vms';

if (!We7::pdo_tableexists($tb_name . '_package')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_package` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `device_id` INT NOT NULL , 
    `title` VARCHAR(128) NOT NULL , 
    `price` INT NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`device_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);    
}

if (!We7::pdo_tableexists($tb_name . '_package_goods')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_package_goods` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `package_id` INT NOT NULL , 
    `goods_id` INT NOT NULL , 
    `price` INT NOT NULL , 
    `num` INT NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`package_id`), 
    INDEX (`goods_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);    
}