<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

/** @var userModelObj $user */
$user = TemplateUtil::getTemplateVar('user');

$tpl_data = TemplateUtil::getTplData([
    'user' => $user->profile(false),
]);

$gift_logs_url = Util::murl('account', ['op' => 'flash_egg', 'fn' => 'gift_logs']);
$lucky_logs_url = Util::murl('account', ['op' => 'flash_egg', 'fn' => 'lucky_logs']);

$tpl_data['js']['code'] = <<<JSCODE
<script>
    const zovye_fn = {}
    
    zovye_fn.redirectGiftLogsPage = function() {
        window.location.href = "$gift_logs_url";
    }
    
    zovye_fn.redirectLuckyLogsPage = function() {
        window.location.href = "$lucky_logs_url";
    }
</script>
JSCODE;

$filename = Theme::getThemeFile(null, 'flash_egg');
Response::showTemplate($filename, ['tpl' => $tpl_data]);