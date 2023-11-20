<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\PaymentConfig;

defined('IN_IA') or exit('Access Denied');

if (PaymentConfig::remove([
    'agent_id' => 0,
    'name' => Pay::SQB,
])) {
    JSON::success('成功！');
}

JSON::fail('失败！');