<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\WxWorkAccount;
use zovye\domain\Account;

$raw = Request::raw();
if (empty($raw)) {
    Response::alert('请重新扫描设备二维码，谢谢！');
}

parse_str($raw, $data);

Log::debug('wxwork', [
    'raw' => $raw,
    'data' => $data,
]);

if (App::isWxWorkEnabled()) {
    WxWorkAccount::cb(Account::WxWORK, $data);
} else {
    Log::debug('wxwork', [
        'error' => '没有启用！',
    ]);
}


exit(WxWorkAccount::CB_RESPONSE);