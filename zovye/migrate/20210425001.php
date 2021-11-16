<?php
namespace zovye;

use zovye\We7;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name . 'zovye_vms')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_wx_app` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `name` VARCHAR(128) NOT NULL , 
    `key` VARCHAR(64) NOT NULL , 
    `secret` VARCHAR(64) NOT NULL , 
    `createtime` INT NOT NULL , PRIMARY KEY (`id`), UNIQUE `key` (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
    
}

    $sql = <<<SQL
CREATE OR REPLACE VIEW `ims_zovye_vms_device_view` AS
SELECT *,
(
    SELECT IFNULL(SUM(o.num),0) FROM `ims_zovye_vms_order` o
	  WHERE o.device_id=d.id AND DATE_FORMAT(now(),"%Y%m")=DATE_FORMAT(FROM_UNIXTIME(o.createtime),"%Y%m")
) AS m_total,
(
    SELECT IFNULL(SUM(o.num),0) FROM `ims_zovye_vms_order` o
	  WHERE o.device_id=d.id AND DATE_FORMAT(now(),"%Y%m%d")=DATE_FORMAT(FROM_UNIXTIME(o.createtime),"%Y%m%d")
) AS d_total
FROM `ims_zovye_vms_device` d;
SQL;
    Migrate::execSQL($sql);