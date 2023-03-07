<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_inventory')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_inventory` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL ,
    `parent_id` INT NOT NULL  DEFAULT '0',
    `uid` VARCHAR(64) NOT NULL , 
    `title` VARCHAR(128) NOT NULL , 
    `extra` TEXT , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    UNIQUE KEY `uid` (`uid`),
    INDEX (`parent_id`)                                     
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ims_zovye_vms_inventory_goods` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `inventory_id` INT NOT NULL , 
    `goods_id` INT NOT NULL , 
    `num` INT NOT NULL DEFAULT '0' , 
    `extra` TEXT , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`goods_id`, `inventory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ims_zovye_vms_inventory_log` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `inventory_id` INT NOT NULL , 
    `goods_id` INT NOT NULL , 
    `num` INT NOT NULL , 
    `extra` TEXT , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`inventory_id`), 
    INDEX (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}
