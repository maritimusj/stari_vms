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
    Response::redirect($home);
    exit();
}

Response::showTemplate('web/migrate/default', [
    'total' => count($task),
]);
