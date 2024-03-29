<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_table_exists($tb_name.'_payload_logs')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_payload_logs` (
     `id` INT NOT NULL AUTO_INCREMENT , 
     `uniacid` INT NOT NULL , 
     `device_id` INT NOT NULL, 
     `goods_id` INT NOT NULL DEFAULT '0', 
     `org` INT NOT NULL DEFAULT '0', 
     `num` INT NOT NULL DEFAULT '0', 
     `extra` TEXT , 
     `createtime` INT NOT NULL , 
     PRIMARY KEY (`id`), 
     INDEX `device_id` (`uniacid`, `device_id`), 
     INDEX `goods_id` (`uniacid`, `goods_id`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);

}