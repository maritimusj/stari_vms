<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

defined('IN_IA') or exit('Access Denied');

/**
 * 闪蛋用户关注页面
 * @param userModelObj $user
 * @param deviceModelObj $device
 * @return void
 */

/** @var deviceModelObj $device */
$device = Util::getTemplateVar('device');

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

$tpl_data = Util::getTplData([
    'user' => $user->profile(),
    'device' => $device->profile(),
]);

$api_url = Util::murl('sample', ['device' => $device->getImei()]);
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

if (User::isSnapshot()) {
    $tpl_data['js']['code'] .= app()->snapshotJs([
        'device_imei' => $device->getImei(),
        'entry' => 'sample',
    ]);
}

$filename = Theme::getThemeFile($device, 'qrcode');
app()->showTemplate($filename, ['tpl' => $tpl_data]);