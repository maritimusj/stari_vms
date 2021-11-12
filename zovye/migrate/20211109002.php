<?php

namespace zovye;

$tb_name = 'zovye_vms';

if (!We7::pdo_fieldexists($tb_name . '_cache')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_cache` CHANGE `expiretime` `expiration` INT(11) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}