<?php
/**
 * 该程序文件是微信第三方平台授权接入回调程序，程序根据需要自动生成！
 *
 * @author jin@stariture.com
 * @url www.stariture.com
 */

$_SERVER['HTTP_USER_AGENT'] = 'api_redirect';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

$_GET['m'] = 'llt_afan';
$_GET['i'] = 2;
$_GET['c'] = 'entry';
$_GET['op'] = 'authcode';
$_GET['do'] = 'wxplatform';

chdir('/www/wwwroot/dev.zovye.com/app');
include './index.php';
