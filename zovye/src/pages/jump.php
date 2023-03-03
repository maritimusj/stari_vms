<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$params = Util::getTemplateVar();

$tpl = is_array($params) ? $params : [];
$js_sdk = Util::fetchJSSDK();

$tpl['js']['code'] = <<<JSCODE
        $js_sdk
        <script>
            wx.ready(function(){
                wx.hideAllNonBaseMenuItem();
            })
        </script>
JSCODE;

$file = Theme::getThemeFile(null, 'jump');
app()->showTemplate($file, ['tpl' => $tpl]);