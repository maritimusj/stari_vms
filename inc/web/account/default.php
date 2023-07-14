<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\accountModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Account::query();

$banned = Request::bool('banned');
if ($banned) {
    $query->where(['state' => Account::BANNED]);
} else {
    $query->where(['state' => Account::NORMAL]);
}

if (Request::isset('type')) {
    $type = Request::int('type');
    if ($type == -1) {
        $all = Account::getAllEnabledThirdPartyPlatform();
        if ($all) {
            $query->where(['type' => $all]);
        } else {
            $query->where(['type' => -1]);
        }
    } else {
        if (empty($type)) {
            $query->where([
                'type' => [
                    Account::NORMAL,
                    Account::AUTH,
                ],
            ]);
        } else {
            $query->where(['type' => Request::int('type')]);
        }
    }
} else {
    if (!App::isDouyinEnabled()) {
        $query->where(['type <>' => Account::DOUYIN]);
    }
    if (!App::isBalanceEnabled()) {
        $query->where(['type <>' => Account::TASK]);
    }
}

if (Request::has('agentId')) {
    $agent = Agent::get(Request::int('agentId'));
    if ($agent) {
        $query->where(['agent_id' => $agent->getId()]);
    }
}

$keywords = Request::trim('keywords', '', true);
if ($keywords) {
    $query->whereOr([
        'name LIKE' => "%$keywords%",
        'title LIKE' => "%$keywords%",
        'descr LIKE' => "%$keywords%",
    ]);
}

$total = $query->count();

$pager = We7::pagination($total, $page, $page_size);

$accounts = [];
if ($total > 0) {
    $query->page($page, $page_size);
    $query->orderBy('order_no DESC');

    /** @var accountModelObj $entry */
    foreach ($query->findAll() as $entry) {
        //过滤掉未启用的吸粉平台
        if ($entry->isThirdPartyPlatform() && $entry->isBanned()) {
            continue;
        }
        $questionnaire = $entry->getConfig('questionnaire', []);
        $data = [
            'id' => $entry->getId(),
            'type' => $entry->getType(),
            'banned' => $entry->isBanned(),
            'agentId' => $entry->getAgentId(),
            'uid' => $entry->getUid(),
            'clr' => $entry->getClr(),
            'orderno' => $entry->getOrderNo(),
            'groupname' => $entry->getGroupName(),
            'name' => $entry->getName(),
            'title' => $entry->getTitle(),
            'descr' => $entry->getDescription(),
            'img' => $entry->getImg(),
            'qrcode' => $entry->getQrcode(),
            'scname' => $entry->getScname(),
            'count' => $entry->getCount(),
            'sccount' => $entry->getSccount(),
            'total' => $entry->getTotal(),
            'orderlimits' => $entry->getOrderLimits(),
            'url' => $questionnaire['url'] ?: $entry->getUrl(),
            'assigned' => !isEmptyArray($entry->get('assigned')),
            'is_third_party_platform' => $entry->isThirdPartyPlatform(),
        ];

        if ($entry->isAuth()) {
            $data['service'] = $entry->getServiceType();
            $data['verified'] = $entry->isVerified();
        } elseif ($entry->isDouyin()) {
            $data['openid'] = $entry->settings('config.openid', '');
        } elseif ($entry->isWxApp()) {
            $data['username'] = $entry->settings('config.username', '');
        } elseif ($entry->isTask()) {
            $data['stats'] = Task::brief($entry);
        }

        if (App::isUseAccountQRCode()) {
            $data['useAccountQRCode'] = $entry->useAccountQRCode();
        }

        //关注多个二维码
        $qrcode_data = $entry->get('qrcodesData', []);
        if ($qrcode_data) {
            $data['more_url'] = [];
            foreach ($qrcode_data as $x) {
                $data['more_url'][] = $x['url'];
            }
        }

        if ($data['agentId']) {
            $agent_x = Agent::get($data['agentId']);
            if ($agent_x) {
                $data['agent'] = [
                    'id' => $agent_x->getId(),
                    'name' => $agent_x->getName(),
                    'avatar' => $agent_x->getAvatar(),
                    'level' => $agent_x->getAgentLevel(),
                ];
            }
        }

        if (App::isCommissionEnabled()) {
            if ($entry->getBonusType() == Account::COMMISSION) {
                $data['commission'] = $entry->getCommissionPrice();
            }
        }

        if (App::isBalanceEnabled()) {
            if ($entry->getBonusType() == Account::BALANCE) {
                $data['balance'] = $entry->getBalancePrice();
            }
        }

        $accounts[] = $data;
    }
}

$navs = [
    [ 'title' => '全部' ],
    [ 'title' => '闪蛋','type' => Account::FlashEgg,'enabled' => App::isFlashEggEnabled() ],
    [ 'title' => '第三方平台', 'type' => -1 ],
    [ 'title' => '公众号', 'type' => Account::NORMAL ],
    [ 'title' => '视频', 'type' => Account::VIDEO ],
    [ 'title' => '抖音', 'type' => Account::DOUYIN, 'enabled' => App::isDouyinEnabled()],
    [ 'title' => '小程序', 'type' => Account::WXAPP ],
    [ 'title' => '问卷', 'type' => Account::QUESTIONNAIRE],
    [ 'title' => '自定义任务', 'type' => Account::TASK, 'enabled' => App::isBalanceEnabled()],
];

Response::showTemplate('web/account/default', [
    'navs' => $navs,
    'agent' => $agent ?? null,
    'accounts' => $accounts,
    'type' => $type ?? null,
    'banned' => $banned,
    'pager' => $pager,
    'keywords' => $keywords,
    'search_url' => $this->createWebUrl('account', ['banned' => $banned]),
]);