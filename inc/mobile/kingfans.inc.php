<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\KingFansAccount;

Log::debug('kingfans', [
    'raw' => Request::raw(),
]);

if (App::isKingFansEnabled()) {
    KingFansAccount::cb(Request::json());
} else {
    Log::debug('kingfans', [
        'error' => '金粉吧没有启用！',
    ]);
}

exit('success');