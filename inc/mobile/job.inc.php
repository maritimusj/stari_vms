<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use Exception;

$op = Request::op('default');
$job_filename = ZOVYE_CORE_ROOT."jobs/$op.php";

try {
    if (file_exists($job_filename)) {
        set_time_limit(0);
        define('IN_JOB', true);
        include_once $job_filename;
    } else {
        Log::error('job', "job file [$job_filename] not exists!");
    }
} catch (JobException $e) {
    Log::error($op, [
        'error' => $e->getMessage(),
        'data' => $e->getData(),
    ]);
} catch (Exception $e) {
    Log::error($op, [
        'error' => $e->getMessage(),
    ]);
}

Job::exit();