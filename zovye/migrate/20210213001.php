<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_principal', 'name')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_principal` ADD `name` VARCHAR(64) NULL DEFAULT NULL AFTER `principal_id`;
SQL;
    Migrate::execSQL($sql);
}