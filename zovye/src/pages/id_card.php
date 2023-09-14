<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$params = TemplateUtil::getTemplateVar();
$tpl = is_array($params) ? $params : [];

$js_sdk = Util::jssdk();

$api_url = Util::murl('idcard', ['tid' => $tpl['tid']]);
$redirect_url = Util::murl('payresult', ['deviceid' => $tpl['deviceId'], 'orderid' => $tpl['tid']]);

$we7_util_url = JS_WE7UTIL_URL;
$jquery_url = JS_JQUERY_URL;

$tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>>
$js_sdk
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }
    zovye_fn.verify = function(name, num) {
        $.getJSON(zovye_fn.api_url, {op: 'verify', name: name, num: num}).then(function(res){
            if (res.status) {
                alert(res.data.msg);
                window.location.replace("$redirect_url");
            } else {
                alert(res.data.msg)
            }
            
            if (res.data.code === 201) {
                zovye_fn.close();
            }
        })
    }
    zovye_fn.refund = function() {
        $.getJSON(zovye_fn.api_url, {op: 'refund'}).then(function(res){
            if (res.data && res.data.msg) {
                alert(res.data.msg);
            }
                       
            zovye_fn.close();
        })
    }    
    zovye_fn.close = function() {
        wx && wx.closeWindow();
    }
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })
</script>
JSCODE;

Response::showTemplate('idcard', ['tpl' => $tpl], true);