<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\giftModelObj;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [
    'navs' => [
        [ 'title' => '全部' ],
        [ 'title' => '闪蛋','type' => Account::FlashEgg,'enabled' => App::isFlashEggEnabled() ],
        [ 'title' => '抽奖活动 <span style="color: red;">*</span>', 'active' => true ],
        [ 'title' => '第三方平台', 'type' => -1 ],
        [ 'title' => '公众号', 'type' => Account::NORMAL ],
        [ 'title' => '视频', 'type' => Account::VIDEO ],
        [ 'title' => '抖音', 'type' => Account::DOUYIN, 'enabled' => App::isDouyinEnabled()],
        [ 'title' => '小程序', 'type' => Account::WXAPP ],
        [ 'title' => '问卷', 'type' => Account::QUESTIONNAIRE],
        [ 'title' => '自定义任务', 'type' => Account::TASK, 'enabled' => App::isBalanceEnabled()],
    ],
];


app()->showTemplate('web/account/lucky', $tpl_data);