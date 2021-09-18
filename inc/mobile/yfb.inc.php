<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('yfb', [
    'raw' => request::raw(),
    'user' => request::str('user'),
    'device' => request::str('device'),
]);

if (App::isYFBEnabled()) {
    YfbAccount::cb([
        'user' => request::str('user'),
        'device' => request::str('device'),
    ]);
} else {
    Util::logToFile('yfb', [
        'error' => '研粉宝没有启用！',
    ]);
}

exit('success');