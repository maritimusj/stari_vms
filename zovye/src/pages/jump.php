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

$tpl['js']['code'] = <<<JSCODE
        $js_sdk
        <script>
            wx.ready(function(){
                wx.hideAllNonBaseMenuItem();
            })
        </script>
JSCODE;

$file = Theme::getThemeFile(null, 'jump');
Response::showTemplate($file, ['tpl' => $tpl]);