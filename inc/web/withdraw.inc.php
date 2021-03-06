<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
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

    $query = CommissionBalance::query(['src' => CommissionBalance::WITHDRAW]);
    $query->where('(updatetime IS NULL OR updatetime=0)');

    $query->orderBy('id desc');

    $list = [];

    /** @var commission_balanceModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $state = $entry->getExtraData('state');
        if (empty($state)) {
            $user = User::get($entry->getOpenid(), true);
            if ($user) {
                $bank = $user->settings('agentData.bank', []);
                $data = [
                    'id' => $entry->getId(),
                    'name' => $user->getName(),
                    'mobile' => "[{$user->getMobile()}]",
                    'xval' => number_format(abs($entry->getXVal()) / 100, 2, '.', ''),
                    'bank' => $bank['bank'],
                    'branch' => $bank['branch'],
                    'realname' => $bank['realname'],
                    'account' => "[{$bank['account']}]",
                    'address' => $bank['address']['province'].$bank['address']['city'],
                    'memo' => '',
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];

                if ($user->isKeeper()) {
                    $keeper = $user->getKeeper();
                    if ($keeper) {
                        $data['memo'] = $keeper->getName();
                    }
                }
                
                $list[] = $data;
            }
        }
    }

    Util::exportExcel('withdraw', ['#', '?????????', '??????', '??????(???)', '?????????', '????????????', '??????', '??????', '???????????????', '??????',  '????????????'], $list);

} elseif ($op == 'default') {

    $tpl_data['mch_pay_enabled'] = !empty(settings('pay.wx.pem'));

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

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
                'remain' => $entry->getExtraData('remain'),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];

            $current = $entry->getExtraData('current');
            if (isset($current)) {
                $data['current'] = number_format($current / 100, 2);
            }

            $remain = $entry->getExtraData('remain');
            if (isset($remain)) {
                $data['remain'] = number_format($remain / 100, 2);
            }

            $fee = $entry->getExtraData('fee');
            if (isset($fee)) {
                $data['fee'] = $fee;
            }

           $memo = $entry->getExtraData('memo', '');
            if (!empty($memo)) {
                $data['memo'] = $memo;
            }

            $state = $entry->getExtraData('state');
            if (empty($state)) {
                $status = '?????????';
            } elseif ($state == 'mchpay') {
                $status = '?????????';
                $MCHPayResult = $entry->getExtraData('mchpayResult');
                $data['paymentNO'] = $MCHPayResult['payment_no'];
            } elseif ($state == 'confirmed') {
                $status = '?????????';
            } elseif ($state == 'cancelled') {
                $status = '?????????';
            } else {
                $status = '??????';
            }

            $data['state'] = $state;
            $data['state_formatted'] = $status;
            if ($entry->getUpdatetime()) {
                $data['updatetime_formatted'] = date('Y-m-d H:i:s', $entry->getUpdatetime());
            }

            //???????????????findAgent????????????????????????????????????????
            /** @var userModelObj $user */
            $user = User::get($entry->getOpenid(), true);
            if ($user) {
                $data['agent'] = [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'avatar' => $user->getAvatar(),
                    'mobile' => $user->getMobile(),
                    'bank' => $user->settings('agentData.bank', []),
                ];

                $user_qrcode = [];
                if ($user->isKeeper()) {
                    $keeper = $user->getKeeper();
                    if ($keeper) {
                        $user_qrcode = $keeper->settings('qrcode', []);
                        $data['keeper'] = [
                            'name' => $keeper->getName(),
                            'mobile' => $keeper->getMobile(),
                        ];
                    }
                }
                if (isEmptyArray($user_qrcode)) {
                    $user_qrcode = $user->settings('qrcode', []);
                }
                if (isset($user_qrcode['wx'])) {
                    $user_qrcode['wx'] = Util::toMedia($user_qrcode['wx']);
                }
                if (isset($user_qrcode['ali'])) {
                    $user_qrcode['ali'] = Util::toMedia($user_qrcode['ali']);
                }
                $data['agent']['qrcode'] = $user_qrcode;
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

    app()->showTemplate('web/withdraw/default', $tpl_data);
}

function getAndCheckWithdraw($id)
{
    /** @var commission_balanceModelObj $balance_obj */
    $balance_obj = CommissionBalance::findOne(['id' => $id, 'src' => CommissionBalance::WITHDRAW]);
    if (empty($balance_obj)) {
        return error(State::ERROR, '??????????????????????????????????????????');
    }

    $openid = $balance_obj->getOpenid();
    $user = User::get($openid, true);
    if (empty($user)) {
        return error(State::ERROR, '????????????????????????');
    }

    if (!$user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
        return error(State::ERROR, '?????????????????????????????????');
    }

    if ($balance_obj->getUpdatetime()) {
        return error(State::ERROR, '??????????????????????????????????????????');
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

    JSON::success('???????????????');

} elseif ($op == 'withdraw_confirm') {

    $balance_obj = getAndCheckWithdraw(request::int('id'));
    if (is_error($balance_obj)) {
        JSON::fail($balance_obj);
    }

    $result = Util::transactionDo(function () use ($balance_obj) {
        if ($balance_obj->update(['state' => 'confirmed', 'admin' => _W('username')], true)) {
            return true;
        }

        return error(State::ERROR, '?????????????????????');
    });

    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('???????????????');

} elseif ($op == 'withdraw_refund') {

    $balance_obj = getAndCheckWithdraw(request::int('id'));
    if (is_error($balance_obj)) {
        JSON::fail($balance_obj);
    }

    $res = Util::transactionDo(
        function () use ($balance_obj) {
            $user = User::get($balance_obj->getOpenid(), true);
            if (empty($user)) {
                return error(State::ERROR, '????????????????????????');
            }

            $commission_balance = $user->getCommissionBalance();

            $total = abs($balance_obj->getXVal());

            //???????????????????????????????????????
            $gcr = $balance_obj->getExtraData('gcr', []);
            if ($gcr && is_array($gcr)) {
                $crs = [];
                foreach ($gcr as $id) {
                    /** @var commission_balanceModelObj $cr */
                    $cr = CommissionBalance::findOne(['id' => $id]);
                    if (empty($cr) || $cr->getExtraData('gid') != $balance_obj->getId()) {
                        return error(State::ERROR, '????????????????????????????????????????????????');
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
                    return error(State::ERROR, '???????????????????????????');
                }

                if (isset($crs)) {
                    foreach ($crs as $cr) {
                        if (!$cr->update(
                            [
                                'refund' => [
                                    'time' => intval($r->getCreatetime()),
                                    'refund_gid' => $r->getId(),
                                ],
                            ],
                            true
                        )) {
                            return error(State::ERROR, '????????????????????????????????????????????????');
                        }
                    }
                }

                if ($balance_obj->update(['state' => 'cancelled', 'refund_id' => $r->getId()], true)) {
                    return ['message' => '???????????????????????????????????????????????????'];
                }
            }

            return error(State::ERROR, '???????????????');
        }
    );

    Util::resultJSON(!is_error($res), ['msg' => $res['message']]);

} elseif ($op == 'stat') {

    $query = CommissionBalance::query([
        'src' => CommissionBalance::WITHDRAW,
    ]);

    //??????
    $date_limit = request::array('datelimit');
    if ($date_limit['start']) {
        $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'].' 00:00:00');
    } else {
        $s_date = new DateTime('first day of this month 00:00:00');
    }

    if ($date_limit['end']) {
        $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'].' 00:00:00');
        $e_date->modify('next day');
    } else {
        $e_date = new DateTime('next day 00:00');
    }

    $query->where([
        'createtime >=' => $s_date->getTimestamp(),
        'createtime <' => $e_date->getTimestamp(),
    ]);

    $agent_openid = request::str('agent_openid');
    if (!empty($agent_openid)) {
        $query->where(['openid' => $agent_openid]);
    }

    $data = [];
    $total = [
        'unconfirmed' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'mchpay' => 0,
    ];

    /** @var commission_balanceModelObj $item */
    foreach ($query->findAll() as $item) {
        $state = $item->getExtraData('state');
        if (empty($state)) {
            $state = 'unconfirmed';
        }
        $created_at = date('Y-m-d', $item->getCreatetime());
        if (!isset($data[$created_at])) {
            $data[$created_at]['unconfirmed'] = 0;
            $data[$created_at]['confirmed'] = 0;
            $data[$created_at]['cancelled'] = 0;
            $data[$created_at]['mchpay'] = 0;
        }
        $val = $item->getXVal();
        $data[$created_at][$state] += $val;
        $total[$state] += $val;
    }

    ksort($data);

    $tpl_data['s_date'] = $s_date->format('Y-m-d');
    $e_date->modify('-1 day');
    $tpl_data['e_date'] = $e_date->format('Y-m-d');
    $tpl_data['open_id'] = $agent_openid;
    $tpl_data['data'] = $data;
    $tpl_data['total'] = $total;

    app()->showTemplate('web/withdraw/stat', $tpl_data);
}
