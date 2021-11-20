<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$raw = request::raw();
if (empty($raw)) {
    Util::resultAlert('请重新扫描设备二维码，谢谢！');
}

parse_str($raw, $data);

Util::logToFile('wxwork', [
    'raw' => $raw,
    'data' => $data,
]);

if (App::isWxWorkEnabled()) {
    WxWorkAccount::cb(Account::WxWORK, $data);
} else {
    Util::logToFile('wxwork', [
        'error' => '没有启用！',
    ]);
}


exit(WxWorkAccount::CB_RESPONSE);