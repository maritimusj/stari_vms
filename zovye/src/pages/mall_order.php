<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

$tpl_data = Util::getTplData([$user]);

$api_url = Util::murl('mall');
$jquery_url = JS_JQUERY_URL;

$js_sdk = Session::fetchJSSDK();

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });
    const zovye_fn = {
        api_url: "$api_url",
    }
    zovye_fn.getOrderList = function(lastId, pagesize) {
        return $.getJSON(zovye_fn.api_url, {op: 'logs', lastId, pagesize});
    }
</script>
JSCODE;

Response::showTemplate('mall_order', ['tpl' => $tpl_data], true);