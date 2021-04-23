<?php

use zovye\We7;

$tb_name = 'zovye_vms';

if (!We7::pdo_tableexists($tb_name . '_referral')) {
    $sql = <<<SQL
RENAME TABLE ims_zovye_vms_referal TO ims_zovye_vms_referral;
SQL;
    zovye\Migrate::execSQL($sql);
}