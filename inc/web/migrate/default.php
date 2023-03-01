<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$task = Migrate::getNewTask();
if (empty($task)) {
    $home = Util::url('homepage');
    Util::redirect($home);
    exit();
}

app()->showTemplate('web/migrate/default', [
    'total' => count($task),
]);
