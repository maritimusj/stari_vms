<?php
namespace zovye;

$tb_name = 'zovye_vms';

if (!We7::pdo_fieldexists($tb_name . '_goods', 'deleted')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods` ADD `deleted` TINYINT NOT NULL DEFAULT '0' AFTER `sync`, ADD INDEX (`deleted`);
SQL;
    Migrate::execSQL($sql);    
}
