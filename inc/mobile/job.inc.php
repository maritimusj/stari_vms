<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
if (in_array($op, [
        'new_agent',
        'agent_app',
        'agent_msg',
        'forward_agent_app',
        'device_err',
        'remain_warning',
        'order',
        'order_stats',
        'account_msg',
        'refund',
        'order_timeout',
        'order_pay_result',
        'adv_review',
        'adv_review_result',
        'device_online',
        'goods_clone',
        'get_result',
        'create_order',
        'create_order_multi',
        'withdraw',
        'account_order',
        'create_order_account',
        'auth_account',
        'repair',
    ]
)) {
    $job_filename = ZOVYE_CORE_ROOT . "jobs/{$op}.php";

    if (file_exists($job_filename)) {
        set_time_limit(0);
        include_once $job_filename;        
    } else {
        Util::logToFile('job', "job file [{$job_filename}] not exists!");
    }    
} else {
    Util::logToFile('job', "job [{$op}] not allowed!");
}

Job::exit();