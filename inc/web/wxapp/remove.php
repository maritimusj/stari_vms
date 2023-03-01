<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$wxapp = WxApp::get(Request::int('id'));
if (empty($wxapp)) {
    JSON::fail('找不到这个小程序！');
}

if ($wxapp->destroy()) {
    JSON::success('删除成功！');
}

JSON::success('删除失败！');