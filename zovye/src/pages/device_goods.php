<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\pages;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Pay;
use zovye\Response;
use zovye\Theme;
use zovye\util\PayUtil;
use zovye\util\TemplateUtil;
use zovye\util\Util;
use function zovye\is_error;

defined('IN_IA') or exit('Access Denied');

$params = TemplateUtil::getTemplateVar();
$tpl = is_array($params) ? $params : [];

/** @var deviceModelObj $device */
$device = $tpl['device']['_obj'];

/** @var userModelObj $user */
$user = $tpl['user']['_obj'];

$api_url = Util::murl('device', ['id' => $device->getId(), 'lane' => $params['lane']]);
$jquery_url = JS_JQUERY_URL;

$js_sdk = Util::jssdk();
$pay_js = Pay::getPayJs($device, $user);
if (is_error($pay_js)) {
    $pay_js = PayUtil::getDummyPayJs();
}

$tpl['js']['code'] = $pay_js;
$tpl['js']['code'] .= <<<JSCODE
        <script src="$jquery_url"></script>
        $js_sdk
        <script>
        wx.ready(function(){
            wx.hideAllNonBaseMenuItem();
        })
        if (typeof zovye_fn === 'undefined') {
            zovye_fn = {};
        }
        zovye_fn.closeWindow = function () {
            wx && wx.ready(function() {
                wx.closeWindow();
            })
        }
        zovye_fn.getDetail = function () {
            return $.get("$api_url");
        }
JSCODE;
$tpl['js']['code'] .= "\r\n</script>";

$file = Theme::getThemeFile($device, 'lane');
Response::showTemplate($file, ['tpl' => $tpl]);