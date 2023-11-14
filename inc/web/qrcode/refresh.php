<?php
namespace zovye;

use zovye\util\QRCodeUtil;
use zovye\util\Util;

$url = Util::shortMobileUrl('qrcode');
$qrcode = QRCodeUtil::createFile('qrcode.'.time(), $url);

updateSettings(
    'misc.qrcode',
    [
        'url' => $url,
        'qrcode' => $qrcode,
    ]
);

JSON::success('二维码已经重新生成！');