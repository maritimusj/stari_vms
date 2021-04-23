<?php
namespace zovye;

$tb_name = 'zovye_vms';

if (!We7::pdo_fieldexists($tb_name . '_device_types', 'device_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device_types` ADD `device_id` INT NOT NULL DEFAULT '0' AFTER `agent_id`, ADD INDEX (`device_id`);
SQL;
    Migrate::execSQL($sql);
}