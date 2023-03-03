<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
/**
 * 抖音页面
 * @param deviceModelObj $device
 * @param userModelObj $user
 * @return void
 */

/** @var deviceModelObj $device */
$device = Util::getTemplateVar('device');

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

$api_url = Util::murl('douyin');
$jquery_url = JS_JQUERY_URL;

$tpl_data = Util::getTplData([$device, $user]);

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }
    zovye_fn.getAccounts = function() {
        return $.getJSON(zovye_fn.api_url, {op: 'account', 'device': '{$device->getShadowId(
)}', 'user': '{$user->getOpenid()}'});
    }
    zovye_fn.redirect = function(uid) {
        return $.getJSON(zovye_fn.api_url, {op: 'detail', 'uid': uid, 'device': '{$device->getShadowId(
)}', 'user': '{$user->getOpenid()}'});
    }
</script>
JSCODE;
$this->showTemplate(Theme::file('douyin'), ['tpl' => $tpl_data]);