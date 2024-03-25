<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$sql = <<<SQL
CREATE OR REPLACE VIEW `ims_zovye_vms_agent_vw` AS
SELECT u.*,p.name as `name`,p.createtime as `updatetime`,
(SELECT count(*) FROM `ims_zovye_vms_device` WHERE agent_id=p.user_id) AS device_total
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

CREATE OR REPLACE VIEW `ims_zovye_vms_promoter_vw` AS
SELECT u.*,p.name as `name`,p.createtime as `updatetime`
FROM 
`ims_zovye_vms_principal` p INNER JOIN `ims_zovye_vms_user` u ON p.user_id=u.id
WHERE p.principal_id=7;

CREATE OR REPLACE VIEW `ims_zovye_vms_inventory_goods_vw` AS
SELECT s.*,g.agent_id,g.name,g.img,g.price FROM `ims_zovye_vms_inventory_goods` s INNER JOIN `ims_zovye_vms_goods` g ON s.goods_id=g.id;

CREATE OR REPLACE VIEW `ims_zovye_vms_task_vw` AS 
SELECT log.*,acc.state FROM `ims_zovye_vms_balance_logs` AS log INNER JOIN `ims_zovye_vms_account` as acc ON log.account_id=acc.id
WHERE type=110;

CREATE OR REPLACE VIEW `ims_zovye_vms_device_keeper_vw` AS 
SELECT d.*,IFNULL(k.keeper_id,0) keeper_id,
k.commission_percent, k.commission_fixed,k.commission_free_percent, k.commission_free_fixed,k.device_qoe_bonus_percent,k.app_online_bonus_percent,
IFNULL(k.kind,0) kind,IFNULL(k.way,0) way FROM `ims_zovye_vms_device` d 
LEFT JOIN `ims_zovye_vms_keeper_devices` k ON d.id=k.device_id WHERE 1;

CREATE OR REPLACE VIEW `ims_zovye_vms_goods_voucher_vw` AS
SELECT v.*,g.name AS goods_name
FROM `ims_zovye_vms_goods_voucher` v
LEFT JOIN  `ims_zovye_vms_goods` g ON v.goods_id=g.id;
SQL;
Migrate::execSQL($sql);