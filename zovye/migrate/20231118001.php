<?php

namespace zovye;

use zovye\domain\Agent;
use zovye\domain\PaymentConfig;
use zovye\model\agentModelObj;

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
        PaymentConfig::createOrUpdateByName(Pay::LCSW, [
            'merchant_no' => $config['lcsw']['merchant_no'],
            'terminal_id' => $config['lcsw']['terminal_id'],
            'access_token' => $config['lcsw']['access_token'],
            'app' => [
                'wx' => [
                    'h5' => boolval($config['lcsw']['wx']),
                    'mini_app' => boolval($config['lcsw']['wxapp']),
                ],
                'ali' => boolval($config['lcsw']['ali']),
            ],
        ]);
    }

    if ($config['SQB'] && $config['SQB']['enable']) {
        PaymentConfig::createOrUpdateByName(Pay::SQB, [
            'sn' => $config['SQB']['terminal_sn'],
            'key' => $config['SQB']['terminal_key'],
            'title' => $config['SQB']['store_name'],
            'app' => [
                'wx' => [
                    'h5' => boolval($config['SQB']['wx']),
                    'mini_app' => boolval($config['SQB']['wxapp']),
                ],
                'ali' => boolval($config['SQB']['ali']),
            ],
        ]);
    }

    if ($config['wx'] && $config['wx']['enable']) {
        PaymentConfig::createOrUpdateByName(Pay::WX, [
            'appid' => $config['wx']['appid'],
            'wxappid' => $config['wx']['wxappid'],
            'mch_id' => $config['wx']['mch_id'],
            'sub_mch_id' => $config['wx']['sub_mch_id'],
            'key' => $config['wx']['key'],
            'pem' => $config['wx']['pem'],
            'app' => [
                'wx' => [
                    'h5' => true,
                    'mini_app' => true,
                ],
            ],
        ]);

        if (!isEmptyArray($config['wx']['v3'])) {
            PaymentConfig::createOrUpdateByName(Pay::WX_V3, [
                'appid' => $config['wx']['appid'],
                'wxappid' => $config['wx']['wxappid'],
                'mch_id' => $config['wx']['mch_id'],
                'sub_mch_id' => '',
                'key' => $config['wx']['v3']['key'],
                'serial' => $config['wx']['v3']['serial'],
                'pem' => $config['wx']['v3']['pem'],
                'app' => [
                    'wx' => [
                        'h5' => true,
                        'mini_app' => true,
                    ],
                ],
            ]);
        }
    }

    $query = Agent::query();
    /** @var agentModelObj $agent */
    foreach ($query->findAll() as $agent) {
        $data = $agent->settings('agentData.pay', []);
        if ($data) {
            if ($data['lcsw'] && $data['lcsw']['enable']) {
                PaymentConfig::createOrUpdate($agent->getId(), Pay::LCSW, [
                    'merchant_no' => $data['lcsw']['merchant_no'],
                    'terminal_id' => $data['lcsw']['terminal_id'],
                    'access_token' => $data['lcsw']['access_token'],
                    'app' => [
                        'wx' => [
                            'h5' => true,
                            'mini_app' => true,
                        ],
                        'ali' => true,
                    ],
                ]);
            }
            if ($data['SQB'] && $data['SQB']['enable']) {
                PaymentConfig::createOrUpdate($agent->getId(), Pay::SQB, [
                    'sn' => $data['SQB']['terminal_sn'],
                    'key' => $data['SQB']['terminal_key'],
                    'title' => $data['SQB']['store_name'],
                    'app' => [
                        'wx' => [
                            'h5' => true,
                            'mini_app' => true,
                        ],
                        'ali' => true,
                    ],
                ]);
            }
            if ($data['wx'] && $data['wx']['enable']) {
                PaymentConfig::createOrUpdate($agent->getId(), Pay::WX_V3, [
                    'sub_mch_id' => $data['wx']['mch_id'],
                    'app' => [
                        'wx' => [
                            'h5' => true,
                            'mini_app' => true,
                        ],
                    ],
                ]);
            }
        }
    }
}