<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_field_exists($tb_name.'_user', 'app')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_user` ADD `app` TINYINT NOT NULL DEFAULT '0' AFTER `state`;
UPDATE `ims_zovye_vms_user` SET `app`=1, `openid`=SUBSTR(`openid`,6) WHERE `openid` REGEXP 'xapp_';
UPDATE `ims_zovye_vms_user` SET `app`=2, `openid`=SUBSTR(`openid`,5) WHERE `openid` REGEXP 'ali_';
SQL;
    Migrate::execSQL($sql);
}