<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('zjbao', [
    'raw' => request::raw(),
]);

if (App::isZJBaoEnabled()) {
    ZhiJinBaoAccount::cb(request::json());
} else {
    Util::logToFile('yunfenba', [
        'error' => '纸巾宝没有启用！',
    ]);
}

exit(ZhiJinBaoAccount::RESPONSE);