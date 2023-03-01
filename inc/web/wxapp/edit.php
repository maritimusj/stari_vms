<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

if (Request::has('id')) {
    $app = WxApp::get(Request::int('id'));
    if (empty($app)) {
        JSON::fail('找不到这个小程序！');
    }
    $tpl_data['wxapp'] = $app;
}

$content = app()->fetchTemplate('web/wxapp/edit_new', $tpl_data);
JSON::success([
    'title' => isset($app) ? '编辑小程序' : '创建小程序',
    'content' => $content,
]);