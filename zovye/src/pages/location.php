<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

/**
 * 获取用户定位页面.
 *
 * @param array $params
 */

$params = Util::getTemplateVar();
$tpl = is_array($params) ? $params : [];

$api_url = Util::murl('util', ['op' => 'location', 'id' => $tpl['device']['shadowId']]);

$jquery_url = JS_JQUERY_URL;
$lbs_key = settings('user.location.appkey', DEFAULT_LBS_KEY);

if (Session::isDouYinAppContainer()) {
    $tpl['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script src="https://mapapi.qq.com/web/mapComponents/geoLocation/v/geolocation.min.js"></script>
<script>
    const zovye_fn = {
        cb: null,
        api_url: "$api_url",
        redirect_url: "{$tpl['redirect']}",
    }
    zovye_fn.check = function(cb) {
		const geolocation = new qq.maps.Geolocation("$lbs_key", "myapp");
		const options = {
			timeout: 8000,
		}
		geolocation.getLocation(
			function success(res) {
                $.getJSON(zovye_fn.api_url, {lng: res.lng, lat: res.lat}).then(function(res){
                    if (res.status) {
                        window.location.replace(zovye_fn.redirect_url);
                    }else{
                        if (typeof cb === 'function') {
                            cb(res.data && res.data.msg || '失败！');
                        }
                    }
                })
			},
			function error() {
				alert("定位失败，请检查是否开启定位功能！")
			}, options);
    }

    zovye_fn.ready = function(fn) {
        $(fn);
    }

    zovye_fn.close = function() {}
</script>
JSCODE;
} else {
    $js_sdk = Session::fetchJSSDK();
    $tpl['js']['code'] = <<<JSCODE
            <script src="$jquery_url"></script>
            <script src="https://mapapi.qq.com/web/mapComponents/geoLocation/v/geolocation.min.js"></script>
            $js_sdk
            <script>
                const zovye_fn = {
                    cb: null,
                    api_url: "$api_url",
                    redirect_url: "{$tpl['redirect']}",
                }
                zovye_fn.check = function(cb) {
                    const geolocation = new qq.maps.Geolocation("$lbs_key", "myapp");
                    const options = {
                        timeout: 8000,
                    }
                    geolocation.getLocation(
                        function success(res) {
                            $.getJSON(zovye_fn.api_url, {lng: res.lng, lat: res.lat}).then(function(res){
                                if (res.status) {
                                    window.location.replace(zovye_fn.redirect_url);
                                }else{
                                    if (typeof cb === 'function') {
                                        cb(res.data && res.data.msg || '失败！');
                                    }
                                }
                            })
                        },
                        function error() {
                            alert("定位失败，请检查是否开启定位功能！")
                        }, options);
                }
            
                zovye_fn.close = function() {
                    wx && wx.closeWindow();
                }
            
                zovye_fn.ready = function(fn) {
                    zovye_fn.cb = fn;
                }
            
                wx.ready(function(){
                    wx.hideAllNonBaseMenuItem();
                    if (typeof zovye_fn.cb === 'function') {
                        zovye_fn.cb();
                    }
                })
            </script>
JSCODE;

    if (User::isSnapshot()) {
        $tpl['js']['code'] .= app()->snapshotJs([
            'device_imei' => $tpl['device']['imei'],
        ]);
    }
}

Response::showTemplate('location', ['tpl' => $tpl], true);