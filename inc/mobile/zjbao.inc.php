<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\ZhiJinBaoAccount;

defined('IN_IA') or exit('Access Denied');

Log::debug('zjbao', [
    'raw' => request::raw(),
]);

if (App::isZJBaoEnabled()) {
    ZhiJinBaoAccount::cb(request::json());
} else {
    Log::debug('yunfenba', [
        'error' => '纸巾宝没有启用！',
    ]);
}

exit(ZhiJinBaoAccount::RESPONSE);