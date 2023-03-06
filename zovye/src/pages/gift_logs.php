<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

defined('IN_IA') or exit('Access Denied');

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

$tpl_data = Util::getTplData([
    'user' => $user->profile(false),
]);

$api_url = Util::murl('account', ['op' => 'flash_egg', 'fn' => 'get_gift_logs']);

$tpl_data['js']['code'] .= <<<JSCODE
<script>
    zovye_fn.getList = function(fn) {
        return $.getJSON("$api_url");
</script>
JSCODE;

$filename = Theme::getThemeFile(null, 'gift_logs');
app()->showTemplate($filename, ['tpl' => $tpl_data]);