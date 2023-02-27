<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

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