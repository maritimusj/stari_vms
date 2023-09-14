<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\TemplateUtil;
use zovye\util\Util;

$params = TemplateUtil::getTemplateVar();
$tpl = is_array($params) ? $params : [];

/** @var deviceModelObj $device */
$device = $tpl['device']['_obj'];

/** @var userModelObj $user */
//$user = $tpl['user']['_obj'];

$device_url = empty($params['redirect']) ? Util::murl('entry', ['device' => $device->getShadowId()]) : strval(
    $params['redirect']
);
$device_api_url = Util::murl('device', ['id' => $device->getId()]);
$jquery_url = JS_JQUERY_URL;

$js_sdk = Util::jssdk();
$tpl['max'] = is_numeric($params['max']) ? $params['max'] : 3;
$tpl['text'] = empty($params['text']) ? '设备连接中' : $params['text'];
$tpl['err_msg'] = empty($params['err_msg']) ? '设备不在线，请稍后再试！' : $params['err_msg'];

$tpl['icon'] = [
    'loading' => empty($params['icon']['loading']) ? MODULE_URL.'static/img/loading-puff.svg' : $params['icon']['loading'],
    'success' => empty($params['icon']['success']) ? MODULE_URL.'static/img/smile.svg' : $params['icon']['success'],
    'error' => empty($params['icon']['error']) ? MODULE_URL.'static/img/offline.svg' : $params['icon']['error'],
];

$scene = empty($params['scene']) ? 'online' : $params['scene'];
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
        zovye_fn.getDetail = function (cb) {
            $.get("$device_api_url", {op: 'detail'}).then(function (res) {
                if (typeof cb === 'function') {
                    cb(res);
                }
            })
        }
        zovye_fn.isReady = function (cb) {
            $.get("$device_api_url", {op: 'is_ready', scene: '$scene', serial: (new Date()).getTime()}).then(function (res) {
                if (typeof cb === 'function') {
                    cb(res);
                }
            })
        }
        zovye_fn.redirect = function() {
            window.location.replace("$device_url");
        }
JSCODE;
$tpl['js']['code'] .= "\r\n</script>";

$file = Theme::getThemeFile($device, 'prepare');
Response::showTemplate($file, ['tpl' => $tpl]);