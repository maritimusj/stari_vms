<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

/**
 * 闪蛋活动详情面页面
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

$api_url = Util::murl('account', ['op' => 'gift', 'device' => $device->getImei()]);
$reg_url = Util::murl('account', ['op' => 'gift', 'fn' => 'reg', 'device' => $device->getImei()]);
$jquery_url = JS_JQUERY_URL;

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }

    zovye_fn.getDetail = function() {
        return $.getJSON(zovye_fn.api_url, {fn: "data"});
    }
    
    zovye_fn.redirectToRegPage = function(uid) {
        window.location.href= "$reg_url";
    }
</script>
JSCODE;

$filename = Theme::getThemeFile($device, 'gift');
app()->showTemplate($filename, ['tpl' => $tpl_data]);