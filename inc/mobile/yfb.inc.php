<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('yfb', [
    'raw' => request::raw(),
    'user' => request::json('openId'),
    'device' => request::json('params'),
]);

if (App::isYFBEnabled()) {
    YfbAccount::cb(request::json());
} else {
    Util::logToFile('yfb', [
        'error' => '粉丝宝没有启用！',
    ]);
}

exit('success');