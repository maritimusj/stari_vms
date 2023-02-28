<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\YfbAccount;

defined('IN_IA') or exit('Access Denied');

Log::debug('yfb', [
    'raw' => Request::raw(),
    'user' => Request::json('openId'),
    'device' => Request::json('params'),
]);

if (App::isYFBEnabled()) {
    YfbAccount::cb(Request::json());
} else {
    Log::debug('yfb', [
        'error' => '粉丝宝没有启用！',
    ]);
}

exit('success');