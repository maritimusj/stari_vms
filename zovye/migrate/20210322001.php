<?php
namespace zovye;

$sql = <<<SQL
CREATE OR REPLACE VIEW `ims_zovye_vms_agent_vw` AS
SELECT u.*,p.name as `name`,p.createtime as `updatetime`,
(SELECT count(*) FROM `ims_zovye_vms_device` WHERE agent_id=u.id) AS device_total
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=1;

CREATE OR REPLACE VIEW `ims_zovye_vms_partner_vw` AS
SELECT u.*,p.name as `name`,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=2;

CREATE OR REPLACE VIEW `ims_zovye_vms_keeper_vw` AS
SELECT u.*,p.name as `name`,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=3;

CREATE OR REPLACE VIEW `ims_zovye_vms_gspor_vw` AS
SELECT u.*,p.name as `name`,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=4;

CREATE OR REPLACE VIEW `ims_zovye_vms_tester_vw` AS
SELECT u.*,p.name as `name`,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=5;
SQL;

Migrate::execSQL($sql);