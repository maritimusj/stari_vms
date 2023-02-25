<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\YunfenbaAccount;

defined('IN_IA') or exit('Access Denied');

Log::debug('yunfenba', [
    'raw' => request::raw(),
    'user' => request::str('user'),
    'device' => request::str('device'),
    'wxid' => request::str('wxid'),
]);

if (App::isYunfenbaEnabled()) {
    YunfenbaAccount::cb([
        'user' => request::str('user'),
        'device' => request::str('device'),
        'wxid' => request::str('wxid'),
    ]);
} else {
    Log::debug('yunfenba', [
        'error' => '云粉没有启用！',
    ]);
}

exit('success');

