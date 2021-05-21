<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
if ($op == 'default') {

    $task = Migrate::getNewTask();
    if (empty($task)) {
        $home = Util::url('homepage');
        Util::redirect($home);
        exit();
    }

    app()->showTemplate('web/migrate/default', [
        'total' => count($task),
    ]);

} elseif ($op == 'step') {

    for ($i = 0; $i < 3; $i++) {
        $locker = app()->lock();

        if ($locker && $locker->isLocked()) {
            $result = Migrate::step();
            $task = Migrate::getNewTask();

            $response = [
                'result' => $result,
                'remain' => count($task),
            ];
            if (!$result) {
                $response['url'] = Util::url('homepage');
            }
            JSON::success($response);
        } else {
            sleep(1);
        }
    }


    JSON::fail(['msg' => '程序加锁失败！']);
}
