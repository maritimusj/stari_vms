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

/** @var userModelObj $user */
$device = Util::getTemplateVar('device');

/** @var array $gift */
$gift = Util::getTemplateVar('gift');

$tpl_data = Util::getTplData([
    'gift' => $gift,
]);

$api_url = Util::murl('account', [
    'op' => 'gift_detail', 
    'fn' => 'save', 
    'device' => $device->getImei(), 
    'uid' => $gift['uid'],
]);

$jquery_url = JS_JQUERY_URL;

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }
    zovye_fn.save = function(data) {
        return $.getJSON(zovye_fn.api_url, data);
    }
</script>
JSCODE;

$filename = Theme::getThemeFile(null, 'reg');
app()->showTemplate($filename, ['tpl' => $tpl_data]);