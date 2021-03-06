<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'add' || $op == 'edit') {

    $tpl_data = [];

    if (request::has('id')) {
        $wxapp = WxApp::get(request::int('id'));
        if (empty($wxapp)) {
            JSON::fail('找不到这个小程序！');
        }
        $tpl_data['wxapp'] = $wxapp;
    }

    $content = app()->fetchTemplate('web/wxapp/edit_new', $tpl_data);
    JSON::success([
        'title' => $op == 'add' ? '创建小程序' : '编辑小程序',
        'content' => $content,
    ]);

} elseif ($op == 'save') {

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
        $wxapp = WxApp::get($params['id']);
        if (empty($wxapp)) {
            JSON::fail('找不到这个小程序！');
        }

        $wxapp->setName($data['name']);
        $wxapp->setKey($data['key']);
        $wxapp->setSecret($data['secret']);

        if (!$wxapp->save()) {
            JSON::fail('保存失败！');
        }

        JSON::success('保存成功！');
    } else {
        if (WxApp::exists(['key' => $data['key']])) {
            JSON::fail('小程序AppID已经存在！');
        }

        $wxapp = WxApp::create($data);
        if (empty($wxapp)) {
            JSON::fail('创建失败！');
        }

        JSON::success('创建成功！');
    }
} elseif ($op == 'remove') {

    $wxapp = WxApp::get(request::int('id'));
    if (empty($wxapp)) {
        JSON::fail('找不到这个小程序！');
    }
    if ($wxapp->destroy()) {
        JSON::success('删除成功！');
    }
    JSON::success('删除失败！');

} elseif ($op == 'addAdvs' || $op == 'editAdvs') {

    $tpl_data = [];

    $content = app()->fetchTemplate('web/wxapp/advs', $tpl_data);
    JSON::success([
        'title' => $op == 'addAdvs' ? '创建广告位' : '编辑广告位',
        'content' => $content,
    ]);

} elseif ($op == 'rewardConfig') {
    $reward = Config::app('wxapp.advs.reward', []);
    $content = app()->fetchTemplate(
        'web/settings/reward_config',
        [
            'config' => $reward,
        ]
    );

    JSON::success(['content' => $content]);

} elseif ($op == 'saveRewardConfig') {

    Config::app('wxapp.advs.reward.bonus', [
        'level0' => [
            'max' => max(0, request::int('numLevel0')),
            'v' => max(0, request::int('bonusLevel0')),
        ],
        'level1' => [
            'max' => max(0, request::int('numLevel1')),
            'v' => max(0, request::int('bonusLevel1')),
        ],
        'level2' => [
            'max' => max(0, request::int('numLevel2')),
            'v' => max(0, request::int('bonusLevel2')),
        ],
    ], true);

    Config::app('wxapp.advs.reward.w', request::str('way'), true);
    Config::app('wxapp.advs.reward.limit', max(0, request::str('limit')), true);
    Config::app('wxapp.advs.reward.max', max(0, request::str('max')), true);
    Config::app('wxapp.advs.reward.allowFree', request::bool('allowFree') ? 1 : 0, true);
    Config::app('wxapp.advs.reward.freeLimit', max(0, request::int('freeLimit')), true);
    Config::app('wxapp.advs.reward.freeCommission', max(0, intval(request::float('freeCommission', 0, 2) * 100)), true);
}