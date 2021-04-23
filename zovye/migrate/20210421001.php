<?php
namespace zovye;

$tb_name = 'zovye_vms';

if (!We7::pdo_fieldexists($tb_name . '_device', 's1')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device` ADD `s3` TINYINT(1) NOT NULL DEFAULT '0' AFTER `shadow_id`, ADD INDEX (`s3`);
ALTER TABLE `ims_zovye_vms_device` ADD `s2` TINYINT(1) NOT NULL DEFAULT '0' AFTER `shadow_id`, ADD INDEX (`s2`);
ALTER TABLE `ims_zovye_vms_device` ADD `s1` TINYINT(1) NOT NULL DEFAULT '0' AFTER `shadow_id`, ADD INDEX (`s1`);

CREATE OR REPLACE VIEW `ims_zovye_vms_agent_vw` AS
SELECT u.*,p.createtime as `updatetime`,
(SELECT count(*) FROM `ims_zovye_vms_device` WHERE agent_id=u.id) AS device_total
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=1;
SQL;
    Migrate::execSQL($sql);    
}
