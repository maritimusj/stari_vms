<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_cache', 'expiration')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_cache` CHANGE `expiretime` `expiration` INT(11) NOT NULL;
SQL;
    Migrate::execSQL($sql);
}