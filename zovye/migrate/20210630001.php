<?php

namespace zovye;

$tb_name = 'zovye_vms';
if (!We7::pdo_fieldexists($tb_name . '_inventory', 'src_inventory_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_llt_afan_inventory_log` ADD `src_inventory_id` INT NOT NULL DEFAULT '0' AFTER `id`;
SQL;
    Migrate::execSQL($sql);
}
