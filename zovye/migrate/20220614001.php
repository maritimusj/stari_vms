<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_fieldexists($tb_name.'_device_groups', 'extra')) {
    $sql = <<<SQL
    ALTER TABLE `ims_zovye_vms_device_groups` ADD `type_id` INT NOT NULL DEFAULT '0' AFTER `uniacid`, ADD INDEX (`type_id`);
    ALTER TABLE `ims_zovye_vms_device_groups` ADD `extra` JSON NOT NULL AFTER `agent_id`;
SQL;
    Migrate::execSQL($sql);
}