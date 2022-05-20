<?php

use zovye\We7;

$tb_name = APP_NAME;

if (!We7::pdo_indexexists($tb_name.'_commission_balance', 'src')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_commission_balance` ADD INDEX(`src`);
SQL;
    zovye\Migrate::execSQL($sql);
}