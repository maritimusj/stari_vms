<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\wx_appModelObj;

$tpl_data['navs'] = Util::getSettingsNavs();

if (App::isCustomWxAppEnabled()) {
    $query = WxApp::query();

    $keyword = request::trim('keyword');
    if (!empty($keywords)) {
        $query->where([
            'name REGEXP' => $keyword,
            'key REGEXP' => $keyword,
        ]);
        $tpl_data['s_keyword'] = $keyword;
    }

    $total = $query->count();

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $list = [];
    /** @var wx_appModelObj $wx_app */
    foreach ($query->findAll() as $wx_app) {
        $data = [
            'id' => $wx_app->getId(),
            'name' => $wx_app->getName(),
            'key' => $wx_app->getKey(),
            'secret' => $wx_app->getSecret(),
            'createtime_formatted' => date('Y-m-d H:i:s', $wx_app->getCreatetime()),
        ];
        $list[] = $data;
    }

    $tpl_data['list'] = $list;
}

if (App::isBalanceEnabled()) {
    $tpl_data['advs_position'] = [
        'banner' => [
            'id' => 1,
            'title' => 'Banner广告',
            'description' => '灵活性较高，适用于用户停留较久或访问频繁等场景',
        ],
        'reward' => [
            'id' => 2,
            'title' => '激励广告',
            'description' => '用户观看广告获得奖励，适用于道具解锁或获得积分等场景',
            'balance' => true,
        ],
        'interstitial' => [
            'id' => 3,
            'title' => '插屏广告',
            'description' => '弹出展示广告，适用于页面切换或回合结束等场景',
        ],
        'video' => [
            'id' => 4,
            'title' => '视频广告',
            'description' => '适用于信息流场景或固定位置，展示自动播放的视频广告',
        ],
    ];

    $tpl_data['advsID'] = Config::app('wxapp.advs', []);

    $tpl_data['notify_url'] = Util::murl('wxnotify');
    $config = Config::app('wxapp.message-push', []);
    if (empty($config['token'])) {
        $config['token'] = Util::random(32);
    }

    $tpl_data['config'] = $config;
}

$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/wxapp', $tpl_data);