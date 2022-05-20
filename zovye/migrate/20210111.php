<?php

namespace zovye;


$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_referral')) {
    $sql = <<<SQL
RENAME TABLE ims_zovye_vms_referal TO ims_zovye_vms_referral;
SQL;
    Migrate::execSQL($sql);
}