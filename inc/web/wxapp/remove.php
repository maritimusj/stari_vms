<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$wxapp = WxApp::get(request::int('id'));
if (empty($wxapp)) {
    JSON::fail('找不到这个小程序！');
}

if ($wxapp->destroy()) {
    JSON::success('删除成功！');
}

JSON::success('删除失败！');