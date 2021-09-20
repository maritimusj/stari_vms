<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('yfb', [
    'raw' => request::raw(),
    'user' => request::str('openid'),
    'device' => request::str('macheNumber'),
]);

if (App::isYFBEnabled()) {
    YfbAccount::cb(request::json());
} else {
    Util::logToFile('yfb', [
        'error' => '研粉宝没有启用！',
    ]);
}

exit('success');