<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;
use zovye\model\luckyModelObj;

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

/** @var luckyModelObj $lucky */
$lucky = Util::getTemplateVar('lucky');

/** @var string $code */
$code = Util::getTemplateVar('code');

$tpl_data = Util::getTplData([
    'user' => $user->profile(false),
    'gift' => $lucky->profile(true),
]);

$api_url = Util::murl('account', [
    'op' => 'lucky', 
    'fn' => 'save', 
    'code' => $code,
]);

$js_sdk = Session::fetchJSSDK();
$jquery_url = JS_JQUERY_URL;

$tpl_data['js']['code'] = <<<JSCODE
$js_sdk
<script src="$jquery_url"></script>
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }
    zovye_fn.save = function(data) {
        return $.getJSON(zovye_fn.api_url, data);
    }
    zovye_fn.closeWindow = function () {
        wx && wx.ready(function() {
            wx.closeWindow();
        })
    }
</script>
JSCODE;

$filename = Theme::getThemeFile(null, 'reg');
app()->showTemplate($filename, ['tpl' => $tpl_data]);