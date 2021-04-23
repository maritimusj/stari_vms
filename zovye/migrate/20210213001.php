<?php
namespace zovye;

$tb_name = 'zovye_vms';

if (!We7::pdo_fieldexists($tb_name . '_principal', 'name')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_principal` ADD `name` VARCHAR(64) NULL DEFAULT NULL AFTER `principal_id`;
CREATE OR REPLACE VIEW `ims_zovye_vms_agent_vw` AS
SELECT u.*,p.createtime as `updatetime`,
(SELECT count(*) FROM `ims_zovye_vms_device` WHERE agent_id=u.id) AS deviceTotal
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