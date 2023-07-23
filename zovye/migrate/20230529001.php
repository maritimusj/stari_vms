<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_table_exists($tb_name.'_cv_upload_device')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_cv_upload_device` (
`id` INT NOT NULL AUTO_INCREMENT , 
`uniacid` INT NOT NULL , 
`device_id` INT NOT NULL ,
`createtime` INT NOT NULL , 
 PRIMARY KEY (`id`),
INDEX (`uniacid`, `device_id`)) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}
if (!We7::pdo_table_exists($tb_name.'_cv_upload_order')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_cv_upload_order` (
`id` INT NOT NULL AUTO_INCREMENT , 
`uniacid` INT NOT NULL , 
`order_id` INT NOT NULL ,
`createtime` INT NOT NULL , 
 PRIMARY KEY (`id`),
INDEX (`uniacid`, `order_id`)) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}