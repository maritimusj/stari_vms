<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//以下为页面数据
$params = Util::getTemplateVar();
$tpl = is_array($params) ? $params : [];

$token = Util::random(16);
$redirect_url = Util::murl('entry', ['from' => 'scan', 'device' => $token]);
$js_sdk = Util::fetchJSSDK();
$jquery_url = JS_JQUERY_URL;

$tpl['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    const zovye_fn = {};
    zovye_fn.scan = function(){
        wx.scanQRCode({
            needResult: 1,
            success: function(data) {
                if(data && data.resultStr) {
                    const url = data.resultStr;
                    let result = url.match(/id=(\w*)/);
                    if (!result) {
                        result =  url.match(/device=(\w*)/);
                    }
                    if(result) {
                        const id = result[1];
                        if(id) {
                            window.location.replace("$redirect_url".replace("$token", id));                            
                        }
                    }
                }
            }
        })
    }
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
        zovye_fn.scan();
    })    
    $(function(){
       $(document).on("touchstart", function(e) {
            const target = $(e.target);
            if(!target.hasClass("disable")) target.data("isMoved", 0);
        })
        $(document).on("touchmove", function(e) {
            const target = $(e.target);
            if(!target.hasClass("disable")) target.data("isMoved", 1);
        })
        $(document).on("touchend", function(e) {
            const target = $(e.target);
            if(!target.hasClass("disable") && target.data("isMoved") === 0) target.trigger("tap");
        })
    })
</script>
JSCODE;

app()->showTemplate(Theme::file('scan'), ['tpl' => $tpl]);