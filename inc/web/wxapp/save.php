<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isCustomWxAppEnabled()) {
    JSON::fail('功能未启用！');
}

$params = [];
parse_str(request('params'), $params);

$data = [
    'name' => $params['wxAppName'],
    'key' => $params['wxAppKey'],
    'secret' => $params['wxAppSecret'],
];

if (empty($data['name'])) {
    $data['name'] = '未命名';
}

if (empty($data['key'])) {
    JSON::fail('appID 不能为空！');
}

if ($params['id']) {
    $app = WxApp::get($params['id']);
    if (empty($app)) {
        JSON::fail('找不到这个小程序！');
    }

    $app->setName($data['name']);
    $app->setKey($data['key']);
    $app->setSecret($data['secret']);

    if (!$app->save()) {
        JSON::fail('保存失败！');
    }

    JSON::success('保存成功！');
}

if (WxApp::exists(['key' => $data['key']])) {
    JSON::fail('小程序AppID已经存在！');
}

$app = WxApp::create($data);
if (empty($app)) {
    JSON::fail('创建失败！');
}

JSON::success('创建成功！');