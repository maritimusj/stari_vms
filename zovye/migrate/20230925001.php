<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_field_exists(APP_NAME.'_goods_expire_alert', 'goods_num')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods_expire_alert` ADD `goods_num` INT NOT NULL DEFAULT '1'  AFTER `lane_id`;
SQL;
    Migrate::execSQL($sql);
}