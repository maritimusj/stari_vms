<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ims_zovye_vms_migration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(64) NOT NULL,
  `filename` varchar(256) NOT NULL,
  `result` tinyint(4) NOT NULL DEFAULT '0',
  `error` text NOT NULL,
  `begin` int(11) NOT NULL,
  `end` int(11) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`uniacid`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_weapp_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) NOT NULL DEFAULT '0',
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`),
  KEY `locked_uid` (`uniacid`, `locked_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) NOT NULL DEFAULT '0',
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`),
  KEY `locked_uid` (`uniacid`, `locked_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `uid` varchar(64) NOT NULL,
  `name` varchar(64) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `descr` varchar(255) DEFAULT NULL,
  `img` varchar(255) DEFAULT NULL,
  `qrcode` varchar(255) DEFAULT NULL,
  `clr` varchar(16) DEFAULT NULL,
  `count` smallint(6) DEFAULT '0',
  `sccount` int(11) DEFAULT '0',
  `scname` varchar(5) NOT NULL,
  `total` int(11) DEFAULT '0',
  `order_limits` int(11) DEFAULT '0',
  `order_no` int(11) DEFAULT '0',
  `state` smallint(6) DEFAULT '0',
  `group_name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `shared` tinyint(4) DEFAULT '0',
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uniacid`, `uid`),
  KEY `name` (`uniacid`, `name`),
  KEY `agent_id` (`uniacid`, `agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_advertising` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `state` tinyint(4) NOT NULL DEFAULT '0',
  `agent_id` int(11) DEFAULT '0',
  `type` int(11) DEFAULT '0',
  `title` varchar(255) DEFAULT '""',
  `extra` text,
  `createtime` int(11) DEFAULT '0',
  `updatetime` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `agent_id` (`uniacid`, `agent_id`),
  KEY `type` (`uniacid`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_advs_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `count` int(11) NOT NULL DEFAULT '0',
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(128) DEFAULT NULL,
  `advs_id` int(11) DEFAULT '0',
  `device_id` varchar(64) DEFAULT NULL,
  `account_id` varchar(64) DEFAULT NULL,
  `ip` varchar(32) DEFAULT NULL,
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`uniacid`, `openid`,`advs_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_agent_app` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` varchar(512) DEFAULT NULL,
  `referee` varchar(128) DEFAULT NULL,
  `state` int(11) DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_app_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `level` tinyint(4) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `title` varchar(512) DEFAULT NULL,
  `content` text,
  `total` int(11) DEFAULT '0',
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_commission_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(128) NOT NULL,
  `src` tinyint(4) DEFAULT NULL,
  `x_val` int(11) NOT NULL,
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  `updatetime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`uniacid`, `openid`),
  KEY `createtime` (`uniacid`, `createtime`),
  KEY `src` (`uniacid`, `src`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(128) NOT NULL,
  `src` tinyint(4) DEFAULT NULL,
  `x_val` int(11) NOT NULL,
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`uniacid`, `openid`),
  KEY `createtime` (`uniacid`, `createtime`),
  KEY `src` (`uniacid`, `src`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_component_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appid` varchar(64) NOT NULL,
  `openid` varchar(128) NOT NULL,
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`uniacid`, `user_id`),
  KEY `appid` (`uniacid`, `appid`),
  KEY `openid` (`uniacid`, `openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_data_vw` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `k` varchar(60) NOT NULL,
  `v` varchar(120) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT '0',
  `name` varchar(128) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `remain` int(11) DEFAULT '0',
  `device_type` int(11) NOT NULL DEFAULT '-1',
  `imei` varchar(64) NOT NULL,
  `iccid` varchar(64) NOT NULL,
  `qrcode` varchar(256) DEFAULT NULL,
  `last_online` int(11) DEFAULT NULL,
  `mcb_online` tinyint(4) DEFAULT '0',
  `last_ping` int(11) DEFAULT NULL,
  `app_id` varchar(128) DEFAULT NULL,
  `app_last_online` int(11) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `tags_data` varchar(512) DEFAULT NULL,
  `shadow_id` varchar(64) DEFAULT NULL,
  `s1` tinyint(1) NOT NULL DEFAULT '0',
  `s2` tinyint(1) NOT NULL DEFAULT '0',
  `s3` tinyint(1) NOT NULL DEFAULT '0',
  `locked_uid` varchar(64) DEFAULT 'n/a',
  `error_code` int(11) DEFAULT '0',
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `imei` (`uniacid`, `imei`),
  KEY `app_id` (`uniacid`, `app_id`),
  KEY `agent_id` (`uniacid`, `agent_id`),
  KEY `agent_id2` (`agent_id`),
  KEY `shadow_id` (`uniacid`, `shadow_id`),
  KEY `createtime` (`createtime`),
  KEY `s3` (`uniacid`, `s3`),
  KEY `s2` (`uniacid`, `s2`),
  KEY `s1` (`uniacid`, `s1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `device_uid` varchar(64) NOT NULL,
  `event` tinyint(4) NOT NULL,
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `device_uid` (`uniacid`, `device_uid`(8),`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `text` text NOT NULL,
  `pics` text NOT NULL,
  `device_id` int(11) NOT NULL,
  `remark` varchar(200) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `device_id` (`uniacid`, `device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(64) NOT NULL DEFAULT '',
  `clr` varchar(32) NOT NULL DEFAULT '',
  `agent_id` int(11) NOT NULL DEFAULT '0',
  `createtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `agent_id` (`uniacid`, `agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `level` tinyint(4) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`uniacid`, `title`(16), `level`),
  KEY `createtime` (`uniacid`, `createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `agent_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`uniacid`, `agent_id`),
  KEY `device_id` (`uniacid`, `device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(32) DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `total` int(11) DEFAULT '0',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_goods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `agent_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(256) NOT NULL,
  `img` varchar(512) NOT NULL,
  `price` int(11) NOT NULL DEFAULT '0',
  `sync` tinyint(4) NOT NULL DEFAULT '0',
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`uniacid`, `agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_goods_voucher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `enable` tinyint(4) NOT NULL DEFAULT '0',
  `agent_id` int(11) NOT NULL DEFAULT '0',
  `goods_id` int(11) NOT NULL,
  `total` int(11) NOT NULL DEFAULT '0',
  `extra` text NOT NULL,
  `used` int(11) NOT NULL DEFAULT '0',
  `begin` int(11) NOT NULL DEFAULT '0',
  `end` int(11) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`uniacid`, `agent_id`),
  KEY `goods_id` (`uniacid`, `goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_goods_voucher_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `code` varchar(16) NOT NULL,
  `owner_id` int(11) NOT NULL DEFAULT '0',
  `voucher_id` int(11) NOT NULL DEFAULT '0',
  `goods_id` int(11) NOT NULL DEFAULT '0',
  `begin` int(11) NOT NULL DEFAULT '0',
  `end` int(11) NOT NULL DEFAULT '0',
  `used_time` int(11) NOT NULL DEFAULT '0',
  `used_user_id` int(11) NOT NULL DEFAULT '0',
  `device_id` int(11) NOT NULL DEFAULT '0',
  `createtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`uniacid`, `code`),
  KEY `voucher_id` (`uniacid`, `voucher_id`)  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_gsp_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `uid` varchar(64) NOT NULL,
  `val_type` varchar(16) NOT NULL DEFAULT 'percent',
  `val` int(11) NOT NULL DEFAULT '0',
  `order_types` varchar(6) NOT NULL DEFAULT '',
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_keeper_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `keeper_id` int(11) NOT NULL,
  `commission_percent` int(11) NOT NULL DEFAULT '-1',
  `commission_fixed` int(11) NOT NULL DEFAULT '-1',
  `kind` tinyint(4) NOT NULL DEFAULT '0',
  `way` tinyint(4) NOT NULL DEFAULT '0',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keeper` (`keeper_id`,`device_id`),
  KEY `device` (`device_id`,`keeper_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_keepers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(64) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`uniacid`, `mobile`),
  KEY `agent_id` (`uniacid`, `agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_login_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `src` tinyint(4) NOT NULL DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  `token` varchar(128) NOT NULL,
  `session_key` varchar(64) NOT NULL,
  `openid_x` varchar(64) DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `device_id` varchar(64) DEFAULT NULL,
  `error_code` int(11) DEFAULT NULL,
  `result_code` int(11) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `name` varchar(128) DEFAULT NULL,
  `result` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `device_id` (`uniacid`, `device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_migration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(64) NOT NULL,
  `filename` varchar(256) NOT NULL,
  `result` tinyint(4) NOT NULL DEFAULT '0',
  `error` text NOT NULL,
  `begin` int(11) NOT NULL,
  `end` int(11) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`uniacid`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_msg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `src` int(11) DEFAULT '0',
  `openid` varchar(128) DEFAULT NULL,
  `name` varchar(128) DEFAULT NULL,
  `num` smallint(6) NOT NULL DEFAULT '0',
  `price` int(11) DEFAULT '0',
  `account` varchar(128) DEFAULT NULL,
  `order_id` varchar(32) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL,
  `ip` varchar(32) DEFAULT NULL,
  `extra` text,
  `result_code` int(11) NOT NULL DEFAULT '0',
  `refund` int(11) NOT NULL DEFAULT '0',
  `createtime` int(11) DEFAULT NULL,
  `updatetime` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uniacid` (`uniacid`),
  KEY `device_id` (`uniacid`, `device_id`),
  KEY `order_id` (`uniacid`, `order_id`),
  KEY `agent_id` (`uniacid`, `agent_id`),
  KEY `openid` (`uniacid`, `openid`),
  KEY `result_code` (`uniacid`, `result_code`),
  KEY `refund` (`uniacid`, `refund`),
  KEY `createtime` (`uniacid`, `createtime`),
  KEY `updatetime` (`uniacid`, `updatetime`),
  KEY `account` (`uniacid`, `account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_payload_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `goods_id` int(11) NOT NULL,
  `org` int(11) NOT NULL,
  `num` int(11) NOT NULL,
  `extra` text NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_id` (`uniacid`, `goods_id`),
  KEY `device_id` (`uniacid`, `device_id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_principal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `principal_id` int(11) NOT NULL,
  `name` varchar(64) DEFAULT NULL,
  `enable` tinyint(4) NOT NULL DEFAULT '1',
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index` (`user_id`,`principal_id`,`enable`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_referral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL DEFAULT '0',
  `code` varchar(32) NOT NULL,
  `createtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_replenish` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `device_uid` varchar(64) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `keeper_id` int(11) NOT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0',
  `org` int(11) DEFAULT NULL,
  `num` int(11) NOT NULL,
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `device_uid` (`uniacid`, `device_uid`),
  KEY `agent_id` (`uniacid`, `agent_id`),
  KEY `keeper_id` (`uniacid`, `keeper_id`),
  KEY `goods_id` (`uniacid`, `goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_advertising` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_commission_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`uniacid`, `createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_component_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_device` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_device_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_goods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_keeper` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) NOT NULL DEFAULT '0',
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `locked_uid` (`uniacid`, `locked_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(128) NOT NULL,
  `count` int(11) DEFAULT '0',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`uniacid`, `title`(16))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `state` tinyint(4) DEFAULT '0',
  `app` tinyint(4) NOT NULL DEFAULT '0',
  `openid` varchar(128) NOT NULL,
  `nickname` varchar(128) DEFAULT NULL,
  `avatar` varchar(256) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `passport` varchar(128) DEFAULT NULL,
  `superior_id` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uniacid` (`uniacid`),
  KEY `openid` (`uniacid`, `openid`),
  KEY `mobile` (`uniacid`, `mobile`),
  KEY `superior_id` (`uniacid`, `superior_id`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_user_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `level` tinyint(4) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`uniacid`, `title`(16), `level`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(128) CHARACTER SET utf8 NOT NULL,
  `version` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_voucher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `uid` varchar(64) NOT NULL,
  `title` varchar(64) DEFAULT NULL,
  `x_val` int(11) NOT NULL,
  `owner` varchar(64) DEFAULT NULL,
  `used_time` int(11) DEFAULT NULL,
  `expired_time` int(11) DEFAULT NULL,
  `memo` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uniacid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_weapp_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  `data` text,
  `createtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `name` (`uniacid`, `name`),
  KEY `locked_uid` (`uniacid`, `locked_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_wx_app` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `key` varchar(64) NOT NULL,
  `secret` varchar(64) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`uniacid`, `key`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_data_vw` (
`id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `k` varchar(60) NOT NULL,
  `v` varchar(120) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `k` (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

//$prefix = 'ims_';
//$sql = preg_replace('/ims_/', $prefix, $sql);
$sql = preg_replace('/zovye_vms/', basename(__DIR__), $sql);

pdo_query($sql);
