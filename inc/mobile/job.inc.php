<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
$job_filename = ZOVYE_CORE_ROOT."jobs/$op.php";

if (file_exists($job_filename)) {
    set_time_limit(0);
    define('IN_JOB', true);
    include_once $job_filename;
} else {
    Log::error('job', "job file [$job_filename] not exists!");
}

Job::exit();