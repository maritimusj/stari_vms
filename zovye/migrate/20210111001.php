<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_table_exists($tb_name.'_referral')) {
    $sql = <<<SQL
RENAME TABLE ims_zovye_vms_referal TO ims_zovye_vms_referral;
SQL;
    Migrate::execSQL($sql);
}