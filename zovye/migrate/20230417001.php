<?php

namespace zovye;

use zovye\model\agentModelObj;
use zovye\model\keeper_devicesModelObj;

$tb_name = APP_NAME;

if (We7::pdo_fieldexists($tb_name.'_user', 'passport')) {
    $sql = <<<SQL
ALTER TABLE `ims_zy_saas_user` DROP `passport`;
SQL;
    Migrate::execSQL($sql);
}

if (We7::pdo_fieldexists($tb_name.'_principal', 'enable')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_principal` CHANGE `enable` `enabled` TINYINT(4) NOT NULL DEFAULT '1';
SQL;
    Migrate::execSQL($sql);
}

if (We7::pdo_fieldexists($tb_name.'_referral', 'agent_id')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_referral` CHANGE `agent_id` `user_id` INT(11) NOT NULL DEFAULT '1';
ALTER TABLE `ims_zovye_vms_referral` ADD UNIQUE(`code`);
ALTER TABLE `ims_zovye_vms_referral` ADD INDEX(`user_id`);
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_keeper_devices', 'f20230419')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_keeper_devices` ADD `f20230419` BOOLEAN NOT NULL AFTER `way`;
ALTER TABLE `ims_zovye_vms_keeper_devices` CHANGE `commission_percent` `commission_percent` INT(11) NOT NULL DEFAULT '-1';
SQL;
    Migrate::execSQL($sql);

    /** @var keeper_devicesModelObj $item */
    foreach(m('keeper_devices')->findAll() as $item) {
        $percent = $item->getCommissionPercent();
        if ($percent && $percent != -1) {
            $item->setCommissionPercent($percent * 100);
            $item->save();
        }
    }
}

/** @var agentModelObj $agent */
foreach (Agent::query()->findAll() as $agent) {
    if (empty($agent->getAgentLevel())) {
        $level = $agent->getAgentData('level');
        if ($level) {
            $agent->setAgent($level);
        }
    }
}