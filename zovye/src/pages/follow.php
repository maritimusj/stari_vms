<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

/**
 * 用户关注页面
 * @param userModelObj $user
 * @param deviceModelObj $device
 * @return void
 */

/** @var deviceModelObj $device */
$device = TemplateUtil::getTemplateVar('device');

/** @var userModelObj $user */
$user = TemplateUtil::getTemplateVar('user');

$tpl_data = TemplateUtil::getTplData([
    'user' => $user->profile(),
    'device' => $device->profile(),
]);

$api_url = Util::murl('util', ['device' => $device->getImei()]);
$jquery_url = JS_JQUERY_URL;

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }

    zovye_fn.getQRCode = function() {
        return $.getJSON(zovye_fn.api_url, {op: "qrcode"});
    }
    
    zovye_fn.checkUser = function() {
        return $.getJSON(zovye_fn.api_url, {op: "check"});
    }
    
</script>
JSCODE;

if (Session::isSnapshot()) {
    $tpl_data['js']['code'] .= Response::snapshotJs([
        'device_imei' => $device->getImei(),
        'entry' => 'sample',
    ]);
}

$filename = Theme::getThemeFile($device, 'qrcode');
Response::showTemplate($filename, ['tpl' => $tpl_data]);