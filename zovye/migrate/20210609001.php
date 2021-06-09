<?php
namespace zovye;

use zovye\We7;

$tb_name = 'zovye_vms';

if (!We7::pdo_tableexists($tb_name . 'zovye_vms')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_payload_logs` (
     `id` INT NOT NULL AUTO_INCREMENT , 
     `uniacid` INT NOT NULL , 
     `device_id` INT NOT NULL , 
     `goods_id` INT NOT NULL , 
     `org` INT NOT NULL , 
     `num` INT NOT NULL , 
     `change` INT NOT NULL , 
     `extra` TEXT NOT NULL , 
     `createtime` INT NOT NULL , 
     PRIMARY KEY (`id`), INDEX (`device_id`), INDEX (`goods_id`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
    
}
    Migrate::execSQL($sql);