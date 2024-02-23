<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

//以下为页面数据
$params = TemplateUtil::getTemplateVar();
$tpl = is_array($params) ? $params : [];

$token = Util::getTokenValue();
$redirect_url = Util::murl('entry', ['from' => 'scan', 'device' => $token, 'channel' => '__channel__']);
$js_sdk = Util::jssdk();
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
                    let result = url.match(/id=(\w*)\/?(\d+)?/);
                    if (!result) {
                        result = url.match(/device=(\w*)\/?(\d+)?/);
                    }
                    if(result) {
                        const id = result[1];
                        const ch = result[2] !== undefined ? result[2] : '';
                        if(id) {
                            window.location.replace("$redirect_url".replace("$token", id).replace("__channel__", ch));                            
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

Response::showTemplate('scan', ['tpl' => $tpl], true);