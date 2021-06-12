<?php
namespace zovye;

$tb_name = 'zovye_vms';

if (!We7::pdo_tableexists($tb_name . 'payload_logs')) {
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
     INDEX `device_id` (`device_id`, `uniacid`), 
     INDEX `goods_id` (`goods_id`, `uniacid`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
    
}