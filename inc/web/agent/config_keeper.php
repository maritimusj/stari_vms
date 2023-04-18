<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isPromoterEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

$content = app()->fetchTemplate(
    'web/agent/keeper_config',
    [
        'id' => $keeper->getId(),
        'config' => $keeper->settings('promoter.commission', []),
    ]
);

JSON::success(['title' => "推广员佣金配置", 'content' => $content]);