<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;
use zovye\util\TemplateUtil;
use zovye\util\Util;

/** @var userModelObj $user */
$user = TemplateUtil::getTemplateVar('user');

/** @var userModelObj $user */
$device = TemplateUtil::getTemplateVar('device');

/** @var array $gift */
$gift = TemplateUtil::getTemplateVar('gift');

$tpl_data = TemplateUtil::getTplData([
    'user' => $user->profile(false),
    'gift' => $gift,
]);

$api_url = Util::murl('account', [
    'op' => 'gift', 
    'fn' => 'save',
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
    
    zovye_fn.closeWindow = function () {
        window.history.back();
    }
</script>
JSCODE;

$filename = Theme::getThemeFile($device, 'reg');
Response::showTemplate($filename, ['tpl' => $tpl_data]);