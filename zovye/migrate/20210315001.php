<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_device_types', 'device_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_device_types` ADD `device_id` INT NOT NULL DEFAULT '0' AFTER `agent_id`, ADD INDEX (`device_id`);
SQL;
    Migrate::execSQL($sql);
}