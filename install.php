<?php
/**
 *
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

$sql = <<<SQL
CREATE TABLE `ims_zovye_vms_migration` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` int(11) NOT NULL DEFAULT '0',
    `name` VARCHAR(64) NOT NULL , 
    `filename` VARCHAR(256) NOT NULL , 
    `result` TINYINT NOT NULL  DEFAULT '0', 
    `error` TEXT, 
    `begin` INT NOT NULL , 
    `end` INT NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    UNIQUE (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_weapp_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) NOT NULL DEFAULT '0',
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`name`,`uniacid`),
  KEY `locked_uid` (`locked_uid`,`uniacid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_settings_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  `createtime` int(11) NOT NULL DEFAULT '0',
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  PRIMARY KEY (`id`),
  KEY `name` (`name`,`uniacid`),
  KEY `locked_uid` (`locked_uid`,`uniacid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

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
  `sccount` int(11) DEFAULT  '0',
  `scname` varchar(5) NOT NULL,
  `total` int(11) DEFAULT '0',
  `balance_deduct_num` int(11) NOT NULL DEFAULT '0',
  `order_limits` int(11) DEFAULT '0',
  `order_no` int(11) DEFAULT '0',
  `state` smallint(6) DEFAULT '0',
  `group_name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `shared` tinyint(4) DEFAULT '0',
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

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
  KEY `type` (`type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

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
  KEY `openid` (`openid`, `advs_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_agent_msg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `msg_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `updatetime` int(11) DEFAULT '0',
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `title` varchar(512) DEFAULT NULL,
  `content` text,
  `total` int(11) DEFAULT '0',
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(128) NOT NULL,
  `x_val` int(11) NOT NULL DEFAULT '0',
  `src` smallint(6) NOT NULL DEFAULT '0',
  `memo` varchar(1024) DEFAULT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

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
  KEY `openid` (`openid`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_coupon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `uid` varchar(64) NOT NULL,
  `title` varchar(64) DEFAULT NULL,
  `x_val` int(11) NOT NULL DEFAULT '0',
  `x_require` int(11) DEFAULT '0',
  `owner` varchar(64) DEFAULT NULL,
  `used_time` int(11) DEFAULT NULL,
  `expired_time` int(11) DEFAULT NULL,
  `memo` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT '0',
  `name` varchar(128) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `remain` int(11) DEFAULT '0',
  `reset` int(11) NOT NULL DEFAULT '0',
  `type` varchar(4) DEFAULT NULL,
  `device_type` int(11) NOT NULL  DEFAULT '-1',
  `city` varchar(4) NOT NULL,
  `imei` varchar(64) NOT NULL,
  `iccid` varchar(64) NOT NULL,
  `sig` tinyint(4) DEFAULT NULL,
  `qoe` smallint(6) DEFAULT NULL,
  `qrcode` varchar(256) DEFAULT NULL,
  `last_online` int(11) DEFAULT NULL,
  `mcb_online` tinyint(4) DEFAULT '0',  
  `last_ping` int(11) DEFAULT NULL,
  `app_id` varchar(128) DEFAULT NULL,
  `app_last_online` int(11) DEFAULT NULL,
  `appVersion` varchar(10) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `tags_data` varchar(512) DEFAULT NULL,
  `shadow_id` varchar(64) DEFAULT NULL,
  `locked_uid` varchar(64) DEFAULT 'n/a',
  `error_code` int(11) DEFAULT '0',
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `imei` (`imei`),
  KEY `app_id` (`app_id`),
  KEY `agent_id` (`agent_id`),
  KEY `shadow_id` (`shadow_id`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `device_uid` varchar(64) NOT NULL,
  `event` tinyint(4) NOT NULL,
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `device_uid` (`device_uid`(8),`uniacid`,`event`)
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_keepers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(64) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `extra` text NULL,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`mobile`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_maintance` (
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
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_msg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `src` int(11) DEFAULT '0',
  `openid` varchar(128) DEFAULT NULL,
  `name` varchar(128) DEFAULT NULL,
  `num` smallint(6) NOT NULL DEFAULT '0',
  `price` int(11) DEFAULT '0',
  `balance` int(11) DEFAULT '0',
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
  KEY `device_id` (`device_id`),
  KEY `order_id` (`order_id`),
  KEY `agent_id` (`agent_id`),
  KEY `openid` (`openid`),
  KEY `result` (`result_code`),
  KEY `createtime` (`createtime`),
  KEY `updatetime` (`updatetime`),    
  KEY `account` (`account`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_prize` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(64) NOT NULL,
  `title` varchar(128) DEFAULT NULL,
  `link` varchar(512) DEFAULT NULL,
  `img` varchar(1024) DEFAULT NULL,
  `desc` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_prizelist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `enabled` tinyint(4) DEFAULT '1',
  `title` varchar(255) DEFAULT NULL,
  `name` varchar(64) NOT NULL,
  `percent` tinyint(4) NOT NULL DEFAULT '0',
  `total` int(11) DEFAULT '0',
  `max_count` int(11) DEFAULT '0',
  `begin_time` int(11) DEFAULT '0',
  `end_time` int(11) DEFAULT '0',
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(128) NOT NULL,
  `count` int(11) DEFAULT '0',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`title`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `state` tinyint(4) DEFAULT '0',
  `openid` varchar(128) NOT NULL,
  `nickname` varchar(128) DEFAULT NULL,
  `avatar` varchar(256) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `passport` varchar(128) DEFAULT NULL,
  `superior_id` int(11) DEFAULT NULL,
  `locked_uid` varchar(64) NOT NULL DEFAULT 'n/a',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`),
  KEY `mobile` (`mobile`),
  KEY `superior_id` (`superior_id`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_user_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `level` tinyint(4) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `data` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`title`(16)),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(128) CHARACTER SET utf8 NOT NULL,
  `version` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

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
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_aaf_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `uid` varchar(128) NOT NULL,
  `x_val` int(11) NOT NULL,
  `extra` text,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;

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
  KEY `device_uid` (`device_uid`),
  KEY `agent_id` (`agent_id`),
  KEY `keeper_id` (`keeper_id`),
  KEY `goods_id` (`goods_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `agent_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_goods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `agent_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(256) NOT NULL,
  `img` varchar(512) NOT NULL,
  `price` int(11) NOT NULL DEFAULT '0',
  `sync` TINYINT NOT NULL DEFAULT '0',
  `extra` text,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_keeper_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `keeper_id` int(11) NOT NULL,
  `commission_percent` tinyint(11) NOT NULL DEFAULT '-1',
  `commission_fixed` int(11) NOT NULL DEFAULT '-1',
  `kind` tinyint(4) NOT NULL DEFAULT '0',
  `way` tinyint(4) NOT NULL DEFAULT '0',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keeper` (`keeper_id`, `device_id`),
  KEY `device` (`device_id`, `keeper_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_referral` ( 
`id` INT NOT NULL AUTO_INCREMENT , 
`agent_id` INT NOT NULL DEFAULT '0' , 
`code` VARCHAR(32) NOT NULL , 
`createtime` INT NOT NULL DEFAULT '0' , 
PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 ;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_device_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(64) NOT NULL DEFAULT '',
  `clr` varchar(32) NOT NULL DEFAULT '',
  `agent_id` int(11) NOT NULL DEFAULT '0',
  `createtime` INT NOT NULL DEFAULT '0' , 
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE  IF NOT EXISTS `ims_zovye_vms_goods_voucher` (
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE  IF NOT EXISTS `ims_zovye_vms_goods_voucher_logs` (
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
  UNIQUE KEY `code` (`code`)
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ims_zovye_vms_device_record` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cate` tinyint(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `ims_zovye_vms_device_record` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cate` tinyint(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE OR REPLACE VIEW `ims_zovye_vms_device_view` AS
SELECT *,
(
    SELECT SUM(o.num) FROM `ims_zovye_vms_order` o
	  WHERE o.device_id=d.id AND  DATE_FORMAT(now(),"%Y%m")=DATE_FORMAT(FROM_UNIXTIME(o.createtime),"%Y%m")
) AS m_total,
(
    SELECT SUM(o.num) FROM `ims_zovye_vms_order` o
	  WHERE o.device_id=d.id AND DATE_FORMAT(now(),"%Y%m%d")=DATE_FORMAT(FROM_UNIXTIME(o.createtime),"%Y%m%d")
) AS d_total
FROM `ims_zovye_vms_device` d;

CREATE OR REPLACE VIEW `ims_zovye_vms_users_vw` AS
SELECT *,
(SELECT SUM(x_val) FROM `ims_zovye_vms_balance` b WHERE b.openid=u.openid AND b.uniacid=u.uniacid) AS balance,
(SELECT COUNT(*) FROM `ims_zovye_vms_prize` p WHERE p.openid=u.openid AND p.uniacid=u.uniacid) AS prize_total,
(SELECT COUNT(id) FROM `ims_zovye_vms_order` o WHERE o.openid=u.openid AND o.price=0 AND o.balance=0) AS free_total,
(SELECT COUNT(id) FROM `ims_zovye_vms_order` o WHERE o.openid=u.openid AND o.price>0) AS fee_total,
(SELECT COUNT(id) FROM `ims_zovye_vms_order` o WHERE o.openid=u.openid AND o.balance>0) AS balance_total
FROM `ims_zovye_vms_user` u;

CREATE OR REPLACE VIEW `ims_zovye_vms_agent_vw` AS
SELECT *,
(SELECT count(id) FROM `ims_zovye_vms_device` WHERE agent_id=u.id) AS deviceTotal
FROM `ims_zovye_vms_user` u
WHERE locate('agent', u.passport)>0;

CREATE OR REPLACE VIEW `ims_zovye_vms_goods_stats_vw` AS 
SELECT agent_id,device_id,goods_id AS id,name,sum(num) as total,FROM_UNIXTIME(createtime,'%Y-%m-%d') as date 
FROM `ims_zovye_vms_order` GROUP BY device_id,goods_id,date;

CREATE OR REPLACE VIEW `ims_zovye_vms_device_keeper_view` AS 
SELECT d.*,IFNULL(k.keeper_id,0) keeper_id,k.commission_percent, k.commission_fixed,IFNULL(k.kind,0) kind,IFNULL(k.way,0) way FROM `ims_zovye_vms_device` d 
LEFT JOIN `ims_zovye_vms_keeper_devices` k ON d.id=k.device_id WHERE 1;

CREATE OR REPLACE VIEW `ims_zovye_vms_goods_voucher_view` AS
SELECT v.*,g.name AS goods_name
FROM `ims_zovye_vms_goods_voucher` v
LEFT JOIN  `ims_zovye_vms_goods` g ON v.goods_id=g.id;

CREATE TABLE IF NOT EXISTS `ims_zovye_vms_data_view` (
`id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `k` varchar(60) NOT NULL,
  `v` varchar(120) NOT NULL,
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

//$prefix = 'ims_';
//$sql = preg_replace('/ims_/', $prefix, $sql);
$sql = preg_replace('/zovye_vms/', basename(__DIR__), $sql);

pdo_query($sql);
