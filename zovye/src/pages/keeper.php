<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$params = Util::getTemplateVar();
$tpl = is_array($params) ? $params : [];

$js_sdk = Util::jssdk();

$mobile_url = Util::murl('keeper', ['op' => 'save']);
$we7_util_url = JS_WE7UTIL_URL;
$jquery_url = JS_JQUERY_URL;

$tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })

    const zovye_fn = {};
    zovye_fn.save = function(mobile, success, fail) {
        $.getJSON("$mobile_url", {mobile: mobile}, function(res){
            if (res) {
                if (res.status) {
                    if (typeof success == 'function') {
                        success(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                } else {
                    if (typeof fail == 'function') {
                        fail(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                }
            }
        })
    }

    zovye_fn.close = function() {
        wx && wx.closeWindow();
    }
</script>
JSCODE;

Response::showTemplate('keeper', ['tpl' => $tpl], true);