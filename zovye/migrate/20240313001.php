<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_table_exists(APP_NAME.'_withdraw_vw')) {
    $sql = <<<SQL
CREATE OR REPLACE VIEW `ims_zovye_vms_withdraw_vw` AS 
SELECT b.*,p.name,u.nickname,u.avatar,u.mobile FROM `ims_zovye_vms_commission_balance` b
INNER JOIN `ims_zovye_vms_user` u ON b.openid=u.openid
INNER JOIN `ims_zovye_vms_principal` p ON u.id=p.user_id
WHERE b.src=3;
SQL;
    Migrate::execSQL($sql);
}
