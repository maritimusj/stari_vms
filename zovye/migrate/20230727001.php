<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_payload_logs', 'lane_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_payload_logs` ADD `lane_id` INT NOT NULL DEFAULT '-1' AFTER `device_id`, ADD INDEX (`device_id`,`lane_id`);
SQL;
    Migrate::execSQL($sql);
}
