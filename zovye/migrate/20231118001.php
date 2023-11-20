<?php

namespace zovye;

use zovye\domain\Agent;
use zovye\domain\PaymentConfig;

defined('IN_IA') or exit('Access Denied');

if (!We7::pdo_table_exists(APP_NAME.'_payment_config')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_payment_config` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `agent_id` INT NOT NULL DEFAULT '0', 
    `name` VARCHAR(62) NOT NULL DEFAULT '', 
    `extra` TEXT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`),
    UNIQUE (`uniacid`, `agent_id`, `name`)) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);

    // 创建支付配置
    $config = settings('pay', []);

    if ($config['lcsw'] && $config['lcsw']['enable']) {
        PaymentConfig::create([
            'agent_id' => 0,
            'name' => Pay::LCSW,
            'extra' => [
                'wxapp' => $config['lcsw']['wxapp'],
                'merchant_no' => $config['lcsw']['merchant_no'],
                'terminal_id' => $config['lcsw']['terminal_id'],
                'access_token' => $config['lcsw']['access_token'],
            ],
        ]);
    }

    if ($config['SQB'] && $config['SQB']['enable']) {
        PaymentConfig::create([
            'agent_id' => 0,
            'name' => Pay::SQB,
            'extra' => [

            ],
        ]);
    }

    if ($config['wx'] && $config['wx']['enable']) {
        PaymentConfig::create([
            'agent_id' => 0,
            'name' => Pay::WX,
            'extra' => [
                'appid' => $config['wx']['wxapp'],
                'wxappid' => $config['wx']['wxappid'],
                'mch_id' => $config['wx']['mch_id'],
                'sub_mch_id' => $config['wx']['sub_mch_id'],
                'key' => $config['wx']['key'],
                'pem' => $config['pem'],
            ],
        ]);

        if (!isEmptyArray($config['wx']['v3'])) {
            PaymentConfig::create([
                'agent_id' => 0,
                'name' => Pay::WX_V3,
                'extra' => [
                    'appid' => $config['wx']['wxapp'],
                    'wxappid' => $config['wx']['wxappid'],
                    'mch_id' => $config['wx']['mch_id'],
                    'sub_mch_id' => $config['wx']['sub_mch_id'],
                    'key' => $config['wx']['v3']['key'],
                    'pem' => $config['v3']['pem'],
                ],
            ]);
        }
    }

    $query = Agent::query();
    foreach ($query->findAll() as $agent) {
        $data = $agent->settings('agentData.pay', []);
        if ($data) {
            if ($data['wx'] && $data['wx']['enable']) {
                unset($data['wx']['enable']);
                PaymentConfig::create([
                    'agent_id' => $agent->getId(),
                    'name' => Pay::WX_V3,
                    'extra' => $data['wx'],
                ]);
            }
            if ($data['lcsw'] && $data['lcsw']['enable']) {
                unset($data['lcsw']['enable']);
                PaymentConfig::create([
                    'agent_id' => $agent->getId(),
                    'name' => Pay::LCSW,
                    'extra' => $data['lcsw'],
                ]);
            }
            if ($data['SQB'] && $data['SQB']['enable']) {
                unset($data['SQB']['enable']);
                PaymentConfig::create([
                    'agent_id' => $agent->getId(),
                    'name' => Pay::SQB,
                    'extra' => $data['SQB'],
                ]);
            }
        }
    }
}