<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('kingfans', [
    'raw' => request::raw(),
]);

if (App::isKingFansEnabled()) {
    KingFansAccount::cb(request::json());
} else {
    Util::logToFile('kingfans', [
        'error' => '金粉吧没有启用！',
    ]);
}

exit('success');