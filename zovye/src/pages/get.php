<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

/**
 * 领取页面.
 *
 * @param array $params
 */

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

$params = Util::getTemplateVar();
$tpl = is_array($params) ? $params : [];

/** @var deviceModelObj $device */
$device = $tpl['device']['_obj'];

/** @var userModelObj $user */
//$user = $tpl['user']['_obj'];

if ($device) {
    //格式化广告
    $tpl['slides'] = [];
    $ads = $device->getAds(Advertising::GET_PAGE);
    if ($ads) {
        $url_params = [
            'deviceid' => $tpl['device']['id'],
            'accountid' => $tpl['account']['id'],
        ];
        foreach ($ads as $adv) {
            if ($adv['extra']['images']) {
                foreach ($adv['extra']['images'] as $image) {
                    if ($image) {
                        $url_params['advsid'] = $adv['id'];
                        $tpl['slides'][] = [
                            'id' => intval($adv['id']),
                            'name' => strval($adv['name']),
                            'image' => strval(Util::toMedia($image)),
                            'url' => Util::murl('adsstats', $url_params),
                        ];
                    }
                }
            }
        }
    }
}

$js_sdk = Util::jssdk();

$get_x_url = Util::murl('getx', ['ticket' => $params['user']['ticket']]);
$get_goods_list_url = Util::murl('goodslist', ['free' => true, 'ticket' => $params['user']['ticket']]);

$jquery_url = JS_JQUERY_URL;

$tpl['timeout'] = App::getDeviceWaitTimeout();
$tpl['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })
    const zovye_fn = {};
    zovye_fn.getx = function(fn) {
        $.getJSON("$get_x_url").then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.getGoods = function(id, fn) {
        $.getJSON("$get_x_url", {goodsId: id}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.getGoodsList = function(fn) {
        $.getJSON("$get_goods_list_url").then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
</script>
JSCODE;

$file = Theme::getThemeFile($device, 'get');
Response::showTemplate($file, ['tpl' => $tpl]);