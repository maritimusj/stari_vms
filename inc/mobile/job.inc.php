<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
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
        'douyin',
        'create_order_balance',
        'update_counter',
    ]
)) {
    $job_filename = ZOVYE_CORE_ROOT . "jobs/$op.php";

    if (file_exists($job_filename)) {
        set_time_limit(0);
        define('IN_JOB', true);
        include_once $job_filename;
    } else {
        Log::error('job', "job file [$job_filename] not exists!");
    }
} else {
    Log::warning('job', "job [$op] not allowed!");
}

Job::exit();