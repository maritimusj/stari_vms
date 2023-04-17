<?php

namespace zovye;

use zovye\model\agentModelObj;

$tb_name = APP_NAME;

if (We7::pdo_fieldexists($tb_name.'_user', 'passport')) {
    $sql = <<<SQL
`ims_zovye_vms_user` DROP `passport`;
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

/** @var agentModelObj $agent */
foreach (Agent::query()->findAll() as $agent) {
    if (empty($agent->getAgentLevel())) {
        $level = $agent->getAgentData('level');
        if ($level) {
            $agent->setAgent($level);
        }
    }
}