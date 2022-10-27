<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\goodsModelObj;
use zovye\model\keeper_devicesModelObj;
use zovye\model\keeperModelObj;
use zovye\model\replenishModelObj;
use zovye\model\userModelObj;

$op = request::op('default');

$tpl_data = [
    'user_state_class' => [
        0 => 'normal',
        1 => 'banned',
    ],
    'op' => $op,
];

if ($op == 'default') {

    $tpl_data['agent_levels'] = settings('agent.levels');

    $tpl_data['commission_enabled'] = App::isCommissionEnabled();
    $tpl_data['balance_enabled'] = App::isBalanceEnabled();

    $credit_used = settings('we7credit.enabled');

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    //身份
    $s_principal = request::trim('s_principal');
    if ($s_principal) {
        switch ($s_principal) {
            case User::AGENT:
                $query = Principal::agent();
                break;
            case User::PARTNER:
                $query = Principal::partner();
                break;
            case User::KEEPER:
                $query = Principal::keeper();
                break;
            case User::TESTER:
                $query = Principal::tester();
                break;
            case User::GSPOR:
                $query = Principal::gspsor();
                break;
        }
    }

    if (empty($query)) {
        $query = User::query();
    }

    //搜索用户名
    $s_keywords = urldecode(request::trim('s_keywords'));
    if ($s_keywords) {
        if (in_array($s_principal, [User::AGENT, User::PARTNER, User::KEEPER, User::TESTER, User::GSPOR])) {
            $query->whereOr([
                'name LIKE' => "%$s_keywords%",
                'nickname LIKE' => "%$s_keywords%",
                'mobile LIKE' => "%$s_keywords%",
                'openid LIKE' => "%$s_keywords%",
            ]);
        } else {
            $query->whereOr([
                'nickname LIKE' => "%$s_keywords%",
                'mobile LIKE' => "%$s_keywords%",
                'openid LIKE' => "%$s_keywords%",
            ]);
        }
    }

    $types = [];
    $s_type_wx = request::bool('s_type_wx');
    if ($s_type_wx) {
        $types[] = User::WX;
    }

    $s_type_wxapp = request::bool('s_type_wxapp');
    if ($s_type_wxapp) {
        $types[] = User::WxAPP;
    }

    $s_type_ali = request::bool('s_type_ali');
    if ($s_type_ali) {
        $types[] = User::ALI;
    }

    $s_type_douyin = request::bool('s_type_douyin');
    if ($s_type_douyin) {
        $types[] = User::DouYin;
    }

    $s_type_api = request::bool('s_type_api');
    if ($s_type_api) {
        $types[] = User::API;
    }

    $s_type_third = request::bool('s_type_third');
    if ($s_type_third) {
        $types[] = User::THIRD_ACCOUNT;
    }

    //当指定了**部分**用户类型时，加入用户app条件过滤
    if ($types && count($types) < 3) {
        $query->where(['app' => $types]);
    }

    $total = $query->count();

    $query->page($page, $page_size);

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->orderBy('id desc');
    $users = [];
    /** @var  userModelObj $user */
    foreach ($query->findAll() as $user) {
        $data = [
            'id' => $user->getId(),
            'openid' => $user->getOpenid(),
            'nickname' => $user->getNickname(),
            'sex' => $user->settings('fansData.sex'),
            'avatar' => $user->getAvatar(),
            'createtime' => date('Y-m-d H:i:s', $user->getCreatetime()),
            'mobile' => $user->getMobile(),
            'state' => $user->getState(),
            'banned' => $user->isBanned(),
            'isAgent' => $user->isAgent(),
            'isPartner' => $user->isPartner(),
            'isKeeper' => $user->isKeeper(),
            'isTester' => $user->isTester(),
            'isGSPor' => $user->isGSPor(),
            'verified' => $user->isIDCardVerified(),
        ];

        if ($credit_used) {
            $data['credit'] = $user->getWe7credit()->total();
        }

        if ($user->isAgent()) {
            $agent = $user->agent();
            $data['agent'] = $agent->getAgentLevel();

        } elseif ($user->isPartner()) {
            $agent = $user->getPartnerAgent();
            if ($agent) {
                $data['co_agent'] = $agent->profile();
            }
        } elseif ($user->isKeeper()) {
            /** @var keeperModelObj $keeper */
            $keeper = $user->getKeeper();
            if ($keeper) {
                $agent = $keeper->getAgent();
                if ($agent) {
                    $data['co_agent'] = $agent->profile();
                }
            }
        }

        //用户来源信息
        $from_data = $user->get('fromData', []);
        if ($from_data) {
            if (!empty($from_data['device'])) {
                $data['from'] = "来自设备：{$from_data['device']['name']}";
            } elseif (!empty($from_data['account'])) {
                $data['from'] = "来自公众号：{$from_data['account']['name']}，{$from_data['account']['title']}";
            }
        }

        $data_arr = $user->settings('verify_18');
        if ($data_arr['verify']) {
            $data['verified'] = 1;
        }

        $data['type'] = User::getUserCharacter($user);
        $users[] = $data;
    }

    $tpl_data['s_keywords'] = $s_keywords;
    $tpl_data['s_type_wx'] = $s_type_wx;
    $tpl_data['s_type_wxapp'] = $s_type_wxapp;
    $tpl_data['s_type_ali'] = $s_type_ali;
    $tpl_data['s_type_douyin'] = $s_type_douyin;
    $tpl_data['s_type_api'] = $s_type_api;
    $tpl_data['s_type_third'] = $s_type_third;
    $tpl_data['s_principal'] = $s_principal;
    $tpl_data['backer'] = $s_keywords || $s_type_wx || $s_type_wxapp || $s_type_ali || $s_type_douyin || $s_type_api || $s_type_third;

    $tpl_data['users'] = $users;

    app()->showTemplate('web//user/default', $tpl_data);

} elseif ($op == 'user_stats') {

    $ids = request::isset('id') ? [request::int('id')] : request::array('ids');

    $result = [];

    $commission_enabled = App::isCommissionEnabled();
    $balance_enabled = App::isBalanceEnabled();
    $team_enabled = App::isTeamEnabled();

    $query = User::query(['id' => $ids]);

    /** @var userModelObj $user */
    foreach ($query->findAll() as $user) {
        if ($user) {
            $data = [
                'id' => $user->getId(),
            ];

            if (Util::isSysLoadAverageOk()) {
                $data['free'] = $user->getFreeTotal();
                $data['pay'] = $user->getPayTotal();
            }

            if ($commission_enabled) {
                $total = $user->getCommissionBalance()->total();
                $data['commission_balance'] = $total;
                $data['commission_balance_formatted'] = number_format($total / 100, 2);
            }

            if ($balance_enabled) {
                $data['balance'] = $user->getBalance()->total();
            }

            if ($team_enabled) {
                $team = Team::getFor($user);
                if ($team) {
                    $data['team_members'] = Team::findAllMember($team)->count();
                }
                $data['is_member'] = Team::isMember($user);
            }

            $result[] = $data;
        }
    }

    JSON::success($result);

} elseif ($op == 'ban') {

    $id = request::int('id');
    if ($id) {
        $user = User::get($id);

        if ($user) {
            $user->setState($user->getState() == 0 ? 1 : 0);

            if ($user->save()) {
                JSON::success(['msg' => '操作成功！', 'banned' => $user->isBanned()]);
            }
        }
    }

    JSON::fail('操作失败');

} elseif ($op == 'reset_mobile') {

    $id = request::int('id');
    if ($id) {
        $user = User::get($id);
        if (empty($user)) {
            JSON::fail('找不到这个用户！');
        }

        if ($user->isAgent() || $user->isPartner() || $user->isKeeper()) {
            JSON::fail('无法操作，请先删除用户身份！');
        }

        if ($user->setMobile('') && $user->save()) {
            JSON::success('已清除用户的手机号码！');
        }
    }

} elseif ($op == 'reset_idcard_verify') {

    $id = request::int('id');
    if ($id) {
        $user = User::get($id);
        if (empty($user)) {
            JSON::fail('找不到这个用户！');
        }

        if ($user->setIDCardVerified('') && $user->save()) {
            JSON::success('已清除用户的实名认证信息！');
        }
    }

} elseif ($op == 'reset_thirdparty_data') {

    $id = request::int('id');
    if ($id) {
        $user = User::get($id);
        if (empty($user)) {
            JSON::fail('找不到这个用户！');
        }

        if ($user->remove('customData') && $user->save()) {
            JSON::success('已清除用户的第三方平台信息！');
        }
    }

} elseif ($op == 'keeper') {

    $id = request::int('id');
    $result = Util::transactionDo(function () use ($id) {
        $user = User::get($id);
        if (empty($user)) {
            return error(State::ERROR, '找不到这个用户！');
        }

        if (!$user->isKeeper()) {
            return error(State::ERROR, '用户不是运营人员！');
        }

        if (!$user->setKeeper(false)) {
            return error(State::ERROR, '取消身份失败！');
        }

        $keeper = $user->getKeeper();
        if ($keeper) {
            //清除原来的登录信息
            foreach (LoginData::keeper(['user_id' => $keeper->getId()])->findAll() as $entry) {
                $entry->destroy();
            }
            if (!$keeper->destroy()) {
                return error(State::ERROR, '删除数据失败！');
            }
        }

        return true;
    });

    if (is_error($result)) {
        Util::itoast($result['message'], $this->createWebUrl('user', ['principal' => 'keeper']), 'error');
    }

    Util::itoast('取消取消运营人员成功！', $this->createWebUrl('user', ['principal' => 'keeper']), 'success');

} elseif ($op == 'search') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query = User::query();

    $keywords = request::trim('keywords');
    if (!empty($keywords)) {
        $query->whereOr(
            [
                'nickname LIKE' => "%$keywords%",
                'mobile LIKE' => "%$keywords%",
            ]
        );
    }

    $passport = request::trim('passport');
    if (!empty($passport)) {
        $query->where(
            [
                'passport LIKE' => "%$passport%",
            ]
        );
    }

    $total = $query->count();
    $total_page = ceil($total / $page_size);

    $result = [
        'page' => $page,
        'pagesize' => $page_size,
        'totalpage' => $total_page,
        'total' => $total,
        'list' => [],
    ];

    if ($total > 0) {
        $query->orderBy('id desc');
        $query->page($page, $page_size);

        /** @var userModelObj $user */
        foreach ($query->findAll() as $user) {
            $result['list'][] = [
                'id' => $user->getId(),
                'nickname' => $user->getName(),
                'avatar' => $user->getAvatar(),
                'mobile' => $user->getMobile(),
            ];
        }
    }

    exit(json_encode($result));

} elseif ($op == 'keeper_device') {

    $user = User::get(request::int('id'));
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $keeper = $user->getKeeper();
    if (empty($keeper)) {
        JSON::fail('这个用户不是营运人员！');
    }

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query = Device::query(['keeper_id' => $keeper->getId()]);

    $total = $query->count();
    $pager = We7::pagination($total, $page, $page_size);

    $query->orderBy('createtime DESC');
    $query->page($page, $page_size);

    $list = [];
    /** @var keeper_devicesModelObj $item */
    foreach ($query->findAll() as $item) {
        $data = [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'imei' => $item->getImei(),
        ];

        if ($item->getCommissionFixed() != -1) {
            $commission_val = number_format(abs($item->getCommissionFixed()) / 100, 2).'元';
        } else {
            $commission_val = $item->getCommissionPercent().'%';
        }

        $data['commission'] = $commission_val;
        $data['kind'] = $item->getKind();
        $data['way'] = empty($item->getWay()) ? '销售分成' : '补货分成';

        $list[] = $data;
    }

    $content = app()->fetchTemplate(
        'web/user/keeper_device',
        [
            'devices' => $list,
            'pager' => $pager,
        ]
    );

    JSON::success(['title' => '', 'content' => $content]);

} elseif ($op == 'keeper_replenish') {

    //补货记录
    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);
    $pager = '';

    $user = User::get(request::int('id'));
    $reps = [];
    $goods_assoc = [];
    if ($user->isKeeper()) {
        $keeper = $user->getKeeper();
        if ($keeper) {

            $query = m('replenish')->query(We7::uniacid(['keeper_id' => $keeper->getId()]));
            $total = $query->count();

            $goods_arr = [];
            if ($total > 0) {
                $pager = We7::pagination($total, $page, $page_size);
                $query->orderBy('createtime DESC');
                $query->page($page, $page_size);

                $replenish_res = $query->findAll();
                /** @var replenishModelObj $item */
                foreach ($replenish_res as $item) {
                    $data = [
                        'num' => $item->getNum(),
                        'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
                        'goods_id' => $item->getGoodsId(),
                    ];
                    $d_data = json_decode($item->getExtra());
                    $d_name = $d_data->device->name ?? '';
                    $goods_arr[] = $item->getGoodsId();
                    $data['device_name'] = $d_name;

                    $reps[] = $data;
                }

                if (!empty($goods_arr)) {
                    $goods_arr = array_unique($goods_arr);
                    $goods_res = Goods::query()->where('id IN ('.implode(',', $goods_arr).')')->findAll();
                    /** @var goodsModelObj $item */
                    foreach ($goods_res as $item) {
                        $goods_assoc[$item->getId()] = [
                            'name' => $item->getName(),
                        ];
                    }
                }

            }
        }
    }

    $content = app()->fetchTemplate(
        'web/user/keeper_replenish',
        [
            'goods_assoc' => $goods_assoc,
            'reps' => $reps,
            'pager' => $pager,
        ]
    );

    JSON::success(['title' => '', 'content' => $content]);

} elseif ($op == 'month_stat') {

    $user = User::get(request::int('id'));
    $year = request::str('year', (new DateTime())->format('Y'));

    list($years, $data) = Stats::getUserMonthCommissionStatsOfYear($user, $year);

    $content = app()->fetchTemplate(
        'web/user/month_stat',
        [
            'data' => $data,
            'years' => $years && count($years) > 1 ? $years : [],
            'current' => $year,
            'user_id' => $user->getId(),
        ]
    );

    JSON::success(['title' => "<b>{$user->getName()}</b>的收提统计", 'content' => $content]);

} elseif ($op == 'commission_balance_edit') {

    $user = User::get(request::int('id'));
    if (empty($user)) {
        JSON::fail('没有找到这个用户！');
    }

    $content = app()->fetchTemplate(
        'web/common/commission_balance_edit',
        [
            'user' => [
                'id' => $user->getId(),
                'openid' => $user->getOpenid(),
                'nickname' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
                'isAgent' => $user->isAgent(),
                'isPartner' => $user->isPartner(),
                'isKeeper' => $user->isKeeper(),
                'verified' => $user->isIDCardVerified(),
            ],
        ]
    );

    JSON::success(['title' => '调整用户<b>余额</b>', 'content' => $content]);

} elseif ($op == 'commission_balance_save') {

    $user = User::get(request::int('id'));
    if (empty($user)) {
        JSON::fail('没有找到这个用户！');
    }

    $total = intval(request::float('total', 0, 2) * 100);
    if ($total == 0) {
        JSON::fail('金额不能为零！');
    }

    if ($user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
        $memo = request::str('memo');
        $r = $user->commission_change(
            $total,
            CommissionBalance::ADJUST,
            [
                'admin' => _W('username'),
                'ip' => CLIENT_IP,
                'user-agent' => $_SERVER['HTTP_USER_AGENT'],
                'memo' => $memo,
            ]
        );
        if ($r && $r->update([], true)) {
            JSON::success('操作成功 ！');
        }
    }

    JSON::fail('保存数据失败！');

} elseif ($op == 'commission_log') {

    $user = User::get(request::int('id'));
    if ($user) {
        $title = "<b>{$user->getName()}</b>的佣金记录";
        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', 5);

        $query = $user->getCommissionBalance()->log();

        $total = $query->count();

        $pager = '';

        $logs = [];
        if ($total > 0) {
            //检查有佣金记录的用户的佣金用户身份是否存在
            if (!$user->isGSPor()) {
                $user->setPrincipal(User::GSPOR);
                $user->save();
            }

            $pager = We7::pagination($total, $page, $page_size);
            $query->page($page, $page_size);
            $query->orderBy('createtime DESC');

            foreach ($query->findAll() as $entry) {
                $logs[] = CommissionBalance::format($entry);
            }
        }

        $content = app()->fetchTemplate(
            'web/common/commission_log',
            [
                'user' => $user,
                'logs' => $logs,
                'pager' => $pager,
            ]
        );

        JSON::success(['title' => $title, 'content' => $content]);
    }
} elseif ($op == 'balance_log') {

    $user = User::get(request::int('id'));
    if ($user) {
        $title = "<b>{$user->getName()}</b>的积分记录";
        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', 5);

        $query = $user->getBalance()->log();

        $total = $query->count();

        $pager = '';

        $logs = [];
        if ($total > 0) {
            $pager = We7::pagination($total, $page, $page_size);
            $query->page($page, $page_size);
            $query->orderBy('createtime DESC');

            foreach ($query->findAll() as $entry) {
                $logs[] = Balance::format($entry);
            }
        }

        $content = app()->fetchTemplate(
            'web/common/balance_log',
            [
                'user' => $user,
                'logs' => $logs,
                'pager' => $pager,
            ]
        );

        JSON::success(['title' => $title, 'content' => $content]);
    }
} elseif ($op == 'balance_edit') {

    $user = User::get(request::int('id'));
    if (empty($user)) {
        JSON::fail('没有找到这个用户！');
    }

    $content = app()->fetchTemplate(
        'web/common/balance_edit',
        [
            'user' => [
                'id' => $user->getId(),
                'openid' => $user->getOpenid(),
                'nickname' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
            ],
        ]
    );

    JSON::success(['title' => '调整用户<b>积分</b>', 'content' => $content]);

} elseif ($op == 'balance_save') {

    $user = User::get(request::int('id'));
    if (empty($user)) {
        JSON::fail('没有找到这个用户！');
    }

    $total = request::int('total');
    if ($total == 0) {
        JSON::fail('积分数量不能为零！');
    }

    if ($user->acquireLocker(User::BALANCE_LOCKER)) {
        $memo = request::str('memo');
        $r = $user->getBalance()->change(
            $total,
            Balance::ADJUST,
            [
                'admin' => _W('username'),
                'ip' => CLIENT_IP,
                'user-agent' => $_SERVER['HTTP_USER_AGENT'],
                'memo' => $memo,
            ]
        );
        if ($r) {
            JSON::success('操作成功 ！');
        }
    }

    JSON::fail('保存数据失败！');
}