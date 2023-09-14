<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\TemplateUtil;
use zovye\util\Util;

/** @var userModelObj $user */
$user = TemplateUtil::getTemplateVar('user');

/** @var accountModelObj $account */
$account = TemplateUtil::getTemplateVar('account');

/** @var deviceModelObj $device */
$device = TemplateUtil::getTemplateVar('device');

/** @var string $tid */
$tid = TemplateUtil::getTemplateVar('tid');

$tpl_data = TemplateUtil::getTplData([$user, $account]);

$api_url = Util::murl('account', $tid ? ['tid' => $tid] : []);
$jquery_url = JS_JQUERY_URL;

$js_sdk = Util::jssdk();
$serial = REQUEST_ID;
$device_uid = $device ? $device->getShadowId() : '';

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });
    const zovye_fn = {
        api_url: "$api_url",
        answer: {},
    }
    zovye_fn.getData = function() {
        return $.getJSON(zovye_fn.api_url, {op: 'detail', uid: "{$account->getUid()}"});
    }
    zovye_fn.setAnswer = function(uid, data) {
        zovye_fn.answer[uid] = data;
    }
    zovye_fn.submitAnswer = function(data) {
        return $.getJSON(zovye_fn.api_url, {
            op: 'result', 
            uid: "{$account->getUid()}", 
            device: "$device_uid",
            serial: "$serial",
            data: data || zovye_fn.answer,
        });
    }
</script>
JSCODE;

Response::showTemplate('questionnaire', ['tpl' => $tpl_data], true);