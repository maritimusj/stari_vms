<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_principal')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_principal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `principal_id` int(11) NOT NULL,
  `enable` tinyint(4) NOT NULL DEFAULT '1',
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index` (`user_id`,`principal_id`,`enable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);
}
