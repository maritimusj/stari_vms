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

Util::logToFile('aqiinfo', [
    'raw' => $raw,
    'data' => $data,
]);


if (App::isAQiinfoEnabled()) {
    WxWorkAccount::cb($data);
} else {
    Util::logToFile('aqiinfo', [
        'error' => '阿旗数据平台没有启用！',
    ]);
}


exit(WxWorkAccount::CB_RESPONSE);