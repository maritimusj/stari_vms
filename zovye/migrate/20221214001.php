<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_vip')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_vip` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `agent_id` INT NOT NULL , 
    `user_id` INT NOT NULL , 
    `extra` JSON NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX (`agent_id`, `user_id`)) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}