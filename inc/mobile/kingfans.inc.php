<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\KingFansAccount;

defined('IN_IA') or exit('Access Denied');

Log::debug('kingfans', [
    'raw' => request::raw(),
]);

if (App::isKingFansEnabled()) {
    KingFansAccount::cb(request::json());
} else {
    Log::debug('kingfans', [
        'error' => '金粉吧没有启用！',
    ]);
}

exit('success');