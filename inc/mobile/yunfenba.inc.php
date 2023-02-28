<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\YunfenbaAccount;

defined('IN_IA') or exit('Access Denied');

Log::debug('yunfenba', [
    'raw' => Request::raw(),
    'user' => Request::str('user'),
    'device' => Request::str('device'),
    'wxid' => Request::str('wxid'),
]);

if (App::isYunfenbaEnabled()) {
    YunfenbaAccount::cb([
        'user' => Request::str('user'),
        'device' => Request::str('device'),
        'wxid' => Request::str('wxid'),
    ]);
} else {
    Log::debug('yunfenba', [
        'error' => '云粉没有启用！',
    ]);
}

exit('success');

