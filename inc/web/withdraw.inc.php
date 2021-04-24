<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\userModelObj;
use zovye\model\commission_balanceModelObj;

$agent_levels = settings('agent.levels');
$commission_enabled = App::isCommissionEnabled();

$op = request::op('default');

$tpl_data = [
    'op' => $op,
    'agent_levels' => $agent_levels,
    'commission_enabled' => $commission_enabled,
];

if ($op == 'export') {

    set_time_limit(60);

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $query = CommissionBalance::query(['src' => CommissionBalance::WITHDRAW]);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $list = [];

    /** @var commission_balanceModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $state = $entry->getExtraData('state');
        if (empty($state)) {
            $agent = User::get($entry->getOpenid(), true);
            if ($agent) {
                $bank = $agent->settings('agentData.bank', []);
                $data = [
                    'id' => $entry->getId(),
                    'name' => $agent->getName(),
                    'mobile' => $agent->getMobile(),
                    'xval' => number_format(abs($entry->getXVal()) / 100, 2),
                    'bank' => $bank['bank'],
                    'branch' => $bank['branch'],
                    'realname' => $bank['realname'],
                    'account' => $bank['account'],
                    'address' => $bank['address']['province'] . $bank['address']['city'],
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
                $list[] = $data;
            }
        }
    }

    Util::exportExcel('withdraw', ['#', '代理商', '手机', '金额', '开户行', '开户支行', '姓名', '卡号', '开户行地址', '创建时间'], $list);

} elseif ($op == 'default') {

    $tpl_data['mch_pay_enabled'] = empty(settings('pay.wx.pem')) ? false : true;

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $query = CommissionBalance::query(['src' => CommissionBalance::WITHDRAW]);
    //$query->where('(updatetime IS NULL OR updatetime=0)');

    if (request::has('agentId')) {
        $user_x = User::get(request('agentId'));
        if ($user_x) {
            $tpl_data['user'] = $user_x->profile();
            $query->where(['openid' => $user_x->getOpenid()]);
        }
    }

    $total = $query->count();

    $total_page = ceil($total / $page_size);
    if ($page > $total_page) {
        $page = 1;
    }

    $apps = [];
    if ($total > 0) {
        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id desc');

        /** @var commission_balanceModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'xval' => number_format(abs($entry->getXVal()) / 100, 2),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];

            $state = $entry->getExtraData('state');
            if (empty($state)) {
                $status = '审核中';
            } elseif ($state == 'mchpay') {
                $status = '已打款';
                $MCHPayResult = $entry->getExtraData('mchpayResult');
                $data['paymentNO'] = $MCHPayResult['payment_no'];
            } elseif ($state == 'confirmed') {
                $status = '已完成';
            } elseif ($state == 'cancelled') {
                $status = '已退款';
            } else {
                $status = '未知';
            }

            $data['state'] = $state;
            $data['state_formatted'] = $status;
            if ($entry->getUpdatetime()) {
                $data['updatetime_formatted'] = date('Y-m-d H:i:s', $entry->getUpdatetime());
            }

            //为什么不是findAgent?因为用户可能已经不是代理了
            /** @var userModelObj $user */
            $user = User::get($entry->getOpenid(), true);
            if ($user) {
                $user_qrcode = [];
                if ($user->isKeeper()) {
                    $keeper = $user->getKeeper();
                    if ($keeper) {
                        $user_qrcode = $keeper->settings('qrcode', []);
                    }
                } else {
                    $user_qrcode = $user->settings('qrcode', []);
                }
                if (isset($user_qrcode['wx'])) {
                    $user_qrcode['wx'] = Util::toMedia($user_qrcode['wx']);
                }
                if (isset($user_qrcode['ali'])) {
                    $user_qrcode['ali'] = Util::toMedia($user_qrcode['ali']);
                }
                $data['agent'] = [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'avatar' => $user->getAvatar(),
                    'mobile' => $user->getMobile(),
                    'bank' => $user->settings('agentData.bank', []),
                    'qrcode' => $user_qrcode,
                ];
            }
            $app_user_openid = $entry->getExtraData('openid');
            if ($app_user_openid) {
                $app_user = User::get($app_user_openid, true);
                if ($app_user) {
                    $data['name'] = $app_user->getName();
                }
            }
            $apps[] = $data;
        }
    }

    $tpl_data['apps'] = $apps;

    $this->showTemplate('web/withdraw/default', $tpl_data);
}

function getAndCheckWithdraw($id)
{
    /** @var commission_balanceModelObj $balance_obj */
    $balance_obj = CommissionBalance::findOne(['id' => $id, 'src' => CommissionBalance::WITHDRAW]);
    if (empty($balance_obj)) {
        return error(State::ERROR, '操作失败，请刷新页面后再试！');
    }

    $openid = $balance_obj->getOpenid();
    $user = User::get($openid, true);
    if (empty($user)) {
        return error(State::ERROR, '找不到这个用户！');
    }

    if (!$user->lock()) {
        return error(State::ERROR, '用户无法锁定，请重试！');
    }

    if ($balance_obj->getUpdatetime()) {
        return error(State::ERROR, '操作失败，请刷新页面后再试！');
    }

    return $balance_obj;
}

if ($op == 'withdraw_pay') {

    $balance_obj = getAndCheckWithdraw(request::int('id'));
    if (is_error($balance_obj)) {
        JSON::fail($balance_obj);
    }

    $result = Util::transactionDo(function () use ($balance_obj) {
        return CommissionBalance::MCHPay($balance_obj);
    });

    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('转帐成功！');

} elseif ($op == 'withdraw_confirm') {

    $balance_obj = getAndCheckWithdraw(request::int('id'));
    if (is_error($balance_obj)) {
        JSON::fail($balance_obj);
    }

    if ($balance_obj->update(['state' => 'confirmed', 'admin' => _W('username')], true)) {
        JSON::success('操作成功！');
    }

    JSON::fail('操作失败！');

} elseif ($op == 'withdraw_refund') {

    $balance_obj = getAndCheckWithdraw(request::int('id'));
    if (is_error($balance_obj)) {
        JSON::fail($balance_obj);
    }

    $res = Util::transactionDo(
        function () use ($balance_obj) {
            $user = User::get($balance_obj->getOpenid(), true);
            if (empty($user)) {
                return error(State::ERROR, '找不到这个用户！');
            }

            $commission_balance = $user->getCommissionBalance();

            $total = abs($balance_obj->getXVal());

            //把手续费等相关费用一起退回
            $gcr = $balance_obj->getExtraData('gcr', []);
            if ($gcr && is_array($gcr)) {
                $crs = [];
                foreach ($gcr as $id) {
                    /** @var commission_balanceModelObj $cr */
                    $cr = CommissionBalance::findOne(['id' => $id]);
                    if (empty($cr) || $cr->getExtraData('gid') != $balance_obj->getId()) {
                        return error(State::ERROR, '处理相关记录出错，请联系管理员！');
                    }

                    $total += abs($cr->getXVal());
                    $crs[] = $cr;
                }
            }

            if ($total > 0) {
                $r = $commission_balance->change(
                    $total,
                    CommissionBalance::REFUND,
                    [
                        'withdraw_id' => $balance_obj->getId(),
                        'admin' => _W('username'),
                    ]
                );

                if (empty($r)) {
                    return error(State::ERROR, '创建退款记录失败！');
                }

                if (isset($crs)) {
                    foreach ($crs as $cr) {
                        if (!$cr->update(
                            [
                                'refund' => [
                                    'time' => intval($r->getCreatetime()),
                                    'refund_gid' => intval($r->getId()),
                                ],
                            ],
                            true
                        )) {
                            return error(State::ERROR, '更新相关记录出错，请联系管理员！');
                        }
                    }
                }

                if ($balance_obj->update(['state' => 'cancelled', 'refund_id' => $r->getId()], true)) {
                    return ['message' => '申请已取消，金额已退款到代理商佣金帐户！'];
                }
            }

            return error(State::ERROR, '操作失败！');
        }
    );

    Util::resultJSON(!is_error($res), ['msg' => $res['message']]);

} elseif ($op == 'stat') {

    //统计
    $date_limit = request::array('datelimit');
    if ($date_limit['start']) {
        $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'] . ' 00:00:00');
    } else {
        $s_date = new DateTime('first day of this month 00:00:00');
    }

    if ($date_limit['end']) {
        $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'] . ' 00:00:00');
        $e_date->modify('next day');
    } else {
        $e_date = new DateTime('first day of next month 00:00:00');
    }

    $condition = [
        'src' => CommissionBalance::WITHDRAW,
        'createtime >=' => $s_date->getTimestamp(),
        'createtime <' => $e_date->getTimestamp(),
    ];

    $agent_openid = request('agent_openid');
    if (!empty($agent_openid)) {
        $condition['openid'] = $agent_openid;
    }

    $res = CommissionBalance::query($condition)->findAll();

    $data = [];
    $total = [
        'unconfirmed' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'mchpay' => 0,
    ];

    /** @var commission_balanceModelObj $item */
    foreach ($res as $item) {

        $state = $item->getExtraData('state');
        if (empty($state)) {
            $state = 'unconfirmed';
        }
        $create_date = date('Y-m-d', $item->getCreatetime());
        if (!isset($data[$create_date])) {
            $data[$create_date]['unconfirmed'] = 0;
            $data[$create_date]['confirmed'] = 0;
            $data[$create_date]['cancelled'] = 0;
            $data[$create_date]['mchpay'] = 0;
        }
        $val = $item->getXVal();
        $data[$create_date][$state] += $val;
        $total[$state] += $val;
    }

    ksort($data);

    $ids = [];
    $query = User::query()->where("passport REGEXP 'gspor'");
    foreach ($query->findAll() as $item) {
        $ids[] = $item->getId();
    }

    $comm_total = 0;
    if (!empty($ids)) {
        $commission_balance = App::isCommissionEnabled();
        $query = User::query(['id' => $ids]);

        /** @var userModelObj $user */
        foreach ($query->findAll() as $user) {
            if ($user) {
                if ($commission_balance) {
                    $total = $user->getCommissionBalance()->total();
                    if ($user->isAgent() || $user->isGSPor() || $user->isKeeper() || $user->isPartner()) {
                        $comm_total += $total;
                    }
                }
            }
        }
    }

    $tpl_data['s_date'] = $s_date->format('Y-m-d');
    $tpl_data['e_date'] = $e_date->format('Y-m-d');
    $tpl_data['open_id'] = $agent_openid;
    $tpl_data['data'] = $data;
    $tpl_data['total'] = $total;
    $tpl_data['comm_total'] = abs(CommissionBalance::query()->get('sum(x_val)'));

    $this->showTemplate('web/withdraw/stat', $tpl_data);
}
