<?php
namespace zovye;

use zovye\We7;

$tb_name = 'zovye_vms';

if (!We7::pdo_tableexists($tb_name . '_principal')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_principal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `principal_id` int(11) NOT NULL,
  `enable` tinyint(4) NOT NULL DEFAULT '1',
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index` (`user_id`,`principal_id`,`enable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW `ims_zovye_vms_agent_vw` AS
SELECT u.*,p.createtime as `updatetime`,
(SELECT count(*) FROM `ims_zovye_vms_device` WHERE agent_id=u.id) AS device_total
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=1;

CREATE OR REPLACE VIEW `ims_zovye_vms_partner_vw` AS
SELECT u.*,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=2;

CREATE OR REPLACE VIEW `ims_zovye_vms_keeper_vw` AS
SELECT u.*,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=3;

CREATE OR REPLACE VIEW `ims_zovye_vms_gspor_vw` AS
SELECT u.*,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=4;
SQL;
    Migrate::execSQL($sql);
}
