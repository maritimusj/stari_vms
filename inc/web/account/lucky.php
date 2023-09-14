<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\business\FlashEgg;
use zovye\domain\Account;
use zovye\model\luckyModelObj;
use zovye\util\Util;

$tpl_data = [
    'navs' => [
        ['title' => '全部'],
        ['title' => '闪蛋', 'type' => Account::FlashEgg, 'enabled' => App::isFlashEggEnabled()],
        ['title' => '抽奖活动 <span style="color: red;">*</span>', 'active' => true],
        ['title' => '第三方平台', 'type' => -1],
        ['title' => '公众号', 'type' => Account::NORMAL],
        ['title' => '视频', 'type' => Account::VIDEO],
        ['title' => '抖音', 'type' => Account::DOUYIN, 'enabled' => App::isDouyinEnabled()],
        ['title' => '小程序', 'type' => Account::WXAPP],
        ['title' => '问卷', 'type' => Account::QUESTIONNAIRE],
        ['title' => '自定义任务', 'type' => Account::TASK, 'enabled' => App::isBalanceEnabled()],
    ],
];

$query = FlashEgg::luckyQuery();

if (Request::has('keywords')) {
    $keywords = Request::trim('keywords');
    if ($keywords) {
        $tpl_data['keywords'] = $keywords;
        $query->whereOr([
            'name LIKE' => "%$keywords%",
        ]);
    }
}

$total = $query->count();

$list = [];
if ($total > 0) {
    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    /** @var luckyModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = $entry->profile(true);

        $agent = $entry->getAgent();
        if ($agent) {
            $data['agent'] = $agent->profile(false);
        }

        $list[] = $data;
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
}

$tpl_data['list'] = $list;
$tpl_data['search_url'] = Util::url('account', ['op' => 'lucky']);

Response::showTemplate('web/account/lucky', $tpl_data);