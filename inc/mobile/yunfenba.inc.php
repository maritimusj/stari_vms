<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('yunfenba', [
    'raw' => request::raw(),
    'user' => request::str('user'),
    'device' => request::str('device'),
]);

if (App::isYunfenbaEnabled()) {
    YunfenbaAccount::cb([
        'user' => request::str('user'),
        'device' => request::str('device'),
    ]);
} else {
    Util::logToFile('yunfenba', [
        'error' => '云粉没有启用！',
    ]);
}

exit('success');

