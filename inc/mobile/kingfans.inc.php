<?php
namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('kingfans', [
    'raw' => request::raw(),
    'user' => request::str('user'),
    'device' => request::str('device'),
]);

if (App::isKingFansEnabled()) {
    KingFansAccount::cb([
        'user' => request::str('user'),
        'device' => request::str('device'),
    ]);
} else {
    Util::logToFile('kingfans', [
        'error' => '金粉吧没有启用！',
    ]);
}

exit('success');