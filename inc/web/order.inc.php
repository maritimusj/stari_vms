<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\orderModelObj;
use zovye\model\user_logsModelObj;

$op = request::op('default');
$is_ajax = request::is_ajax();

$tpl_data = [
    'op' => $op,
    'commission_balance' => App::isCommissionEnabled(),
];

if ($op == 'default') {

    $query = Order::query();

    $agent_openid = request::str('agent_openid');
    if (!empty($agent_openid)) {
        $agent = Agent::get($agent_openid, true);
        if ($agent) {
            $query->where($agent);

            $tpl_data['s_open_id'] = $agent_openid;
            $tpl_data['ag_res'][] = [
                'openid' => $agent->getOpenid(),
                'nickname' => $agent->getName(),
                'mobile' => $agent->getMobile(),
            ];
        }
    }

    $device_id = request::int('device_id');
    if ($device_id) {
        $device = Device::get($device_id);
        if ($device) {
            $query->where($device);

            $tpl_data['s_device_id'] = $device_id;
            $tpl_data['de_res'][] = [
                'id' => $device->getId(),
                'name' => $device->getName(),
                'imei' => $device->getImei(),
            ];
        }
    }

    $user_id = request::int('user_id');
    if ($user_id) {
        $user = User::get($user_id);
        if ($user) {
            $query->where($user);

            $tpl_data['s_user_id'] = $user->getId();
            $tpl_data['user_res'] = $user->profile();
        }
    }

    if (request::has('account_id')) {
        $account = Account::get(request::int('account_id'));
        if ($account) {
            $query->where(['account' => $account->getName()]);
        }
    }

    $way = request::str('way');
    if ($way == 'free') {
        $query->where(['src' => [Order::FREE, Order::ACCOUNT]]);
    } elseif ($way == 'pay') {
        $query->where(['src' => Order::PAY]);
    } elseif ($way == 'balance') {
        $query->where(['src' => Order::BALANCE]);
    }  elseif ($way == 'charging') {
        $query->where(['src' => [Order::CHARGING, Order::CHARGING_UNPAID]]);
    } elseif ($way == 'refund') {
        $query->where(['refund' => 1]);
        if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
            $query->where(['src' => Order::PAY]);
        }
    } elseif ($way == 'unexpected') {
        $query->where(['result_code >' => 0]);
    }

    $keyword = request::str('keyword');
    if ($keyword) {
        $query->whereOr([
            'nickname LIKE' => "%$keyword%",
            'account LIKE' => "%$keyword%",
        ]);

        $tpl_data['s_keyword'] = $keyword;
    }

    $order_no = request::str('order');
    if ($order_no) {
        $query->whereOr([
            'order_id LIKE' => "%$order_no%",
            'extra REGEXP' => "\"transaction_id\":\"[0-9]*{$order_no}[0-9]*\"",
        ]);
        $tpl_data['s_order'] = $order_no;
    }

    $order_no = request::str('orderNO');
    if ($order_no) {
        $query->where(['order_id' => $order_no]);
        $tpl_data['s_order'] = $order_no;
    }

    $limit = request::array('datelimit');
    if ($limit['start']) {
        $start = DateTime::createFromFormat('Y-m-d H:i:s', $limit['start'].' 00:00:00');
        if ($start) {
            $tpl_data['s_start_date'] = $start->format('Y-m-d');
            $query->where(['createtime >=' => $start->getTimestamp()]);
        }
    }

    if ($limit['end']) {
        $end = DateTime::createFromFormat('Y-m-d H:i:s', $limit['end'].' 00:00:00');
        if ($end) {
            $tpl_data['s_end_date'] = $end->format('Y-m-d');
            $end->modify('next day');
            $query->where(['createtime <' => $end->getTimestamp()]);
        }
    }

    $page = max(1, request::int('page'));
    $page_size = $is_ajax ? 10 : request::int('pagesize', DEFAULT_PAGE_SIZE);

    $total = Config::app('order.total');
    if ($total < 100000) {
        $total = $query->count();
        if ($total > 100000) {
            Config::app('order.total', $total, true);
        }
    } else {
        $total = $page * $page_size * 100;
    }

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    $accounts = [];
    $orders = [];

    /** @var orderModelObj $entry */
    foreach ($query->findAll() as $entry) {
        //???????????????
        if (empty($accounts[$entry->getAccount()])) {
            $account = Account::findOneFromName($entry->getAccount());
            if ($account) {
                $profile = [
                    'type' => $account->getType(),
                    'name' => $account->getName(),
                    'clr' => $account->getClr(),
                    'title' => $account->getTitle(),
                    'img' => $account->getImg(),
                ];
                if ($account->isVideo()) {
                    $profile['media'] = $account->getMedia();
                } elseif ($account->isDouyin()) {
                    $profile['douyin'] = true;
                } elseif ($account->isWxApp()) {
                    $profile['wxapp'] = true;
                } elseif ($account->isQuestionnaire()) {
                    $profile['questionnaire'] = true;
                } elseif ($account->isThirdPartyPlatform()) {
                    $profile['third-party-platform'] = true;
                } else {
                    $profile['qrcode'] = $account->getQrcode();
                }
                if ($entry->getExtraData('ticket.questionnaireAccountId')) {
                    $profile['questionnaire+1'] = '?????? ';
                    $account = Account::get($entry->getExtraData('ticket.questionnaireAccountId'));
                    if ($account) {
                        $profile['questionnaire+1'] .= $account->getTitle();
                    } else {
                        $profile['questionnaire+1'] .= 'n/a';
                    }
                }
                $accounts[$entry->getAccount()] = $profile;
            }
        }

        $data = Order::format($entry, true);
        if ($accounts[$data['account']]) {
            if (isset($accounts[$data['account']]['media'])) {
                $data['account_title'] = '?????? '.$accounts[$data['account']]['title'];
            } elseif ($accounts[$data['account']]['douyin']) {
                $data['account_title'] = '?????? '.$accounts[$data['account']]['title'];
            } elseif ($accounts[$data['account']]['wxapp']) {
                $data['account_title'] = '????????? '.$accounts[$data['account']]['title'];
            } elseif ($accounts[$data['account']]['questionnaire']) {
                $data['account_title'] = '?????? '.$accounts[$data['account']]['title'];
                $data['questionnaire_log'] = $entry->getExtraData('ticket.logId', '');
            } elseif ($accounts[$data['account']]['questionnaire+1']) {
                $data['account_title'] = '????????? '.$accounts[$data['account']]['title'].' + '.$accounts[$data['account']]['questionnaire+1'];
                $data['questionnaire_log'] = $entry->getExtraData('ticket.logId', 0);
            } elseif ($accounts[$data['account']]['third-party-platform']) {
                $data['account_title'] = '??????????????? '.$accounts[$data['account']]['title'];
            } else {
                $data['account_title'] = '????????? '.$accounts[$data['account']]['title'];
            }
            $data['clr'] = $accounts[$data['account']]['clr'];
        } else {
            if ($data['balance']) {
                $data['clr'] = '#ffc107';
            } else {
                $data['account_title'] = 'n/a';
                $data['clr'] = '#ccc';
            }
            if ($data['refund']) {
                $data['clr'] = '#ccc';
            }
        }

        //??????
        $commission = $entry->getExtraData('commission', []);
        if ($commission) {
            $data['commission'] = $commission;
        }

        $pay_result = $entry->getExtraData('payResult');
        if ($pay_result) {
            $data['transaction_id'] = $pay_result['transaction_id'] ?? ($pay_result['uniontid'] ?? $data['orderId']);
            $data['src'] = strval($pay_result['from']);
            $data['pay_name'] = strval($pay_result['type']);
        }

        if ($entry->getBluetoothDeviceBUID()) {
            $msg = $entry->getExtraData('bluetooth.error.msg', '');
            $data['result'] = $entry->isBluetoothResultFail() ? err($msg) : [];
        } else {
            $data['result'] = $entry->getExtraData('pull.result', []);
        }
        $device = $entry->getDevice();
        if ($device) {
            $data['pull_logs'] = !$device->isBlueToothDevice() ? true : '??????????????????';
        }
        $orders[] = $data;
    }

    $pager = We7::pagination($total, $page, $page_size);
    if (stripos($pager, '&filter=1') === false) {
        $filter = [
            'agent_openid' => $agent_openid,
            'user_id' => $user_id,
            'device_id' => $device_id,
            'order' => $order_no,
            'datelimit[start]' => isset($start) ? $start->format('Y-m-d') : '',
            'datelimit[end]' => isset($end) ? $end->format('Y-m-d') : '',
            'filter' => 1,
        ];

        foreach ($filter as $index => $entry) {
            if (empty($entry)) {
                unset($filter[$index]);
            }
        }
        $params_str = http_build_query($filter);
        $pager = preg_replace('#href="(.*?)"#', 'href="${1}&'.$params_str.'"', $pager);
    }

    if ($is_ajax) {
        $data = [
            'isajax' => true,
            'orders' => $orders,
            'user' => request::has('user_id'),
            'accounts' => $accounts,
            'pager' => $pager,
        ];

        if (isset($device)) {
            $data['device'] = $device;
        }

        $content = app()->fetchTemplate('web/order/list', $data);

        JSON::success([
            'title' => isset($user) ? '<b>'.$user->getName().'</b>???????????????' : '',
            'content' => $content,
        ]);
    }

    $tpl_data['s_way'] = $way;
    $tpl_data['url'] = Util::url('order', ['way' => $way]);
    $tpl_data['backer'] = $keyword || $agent_openid || $user_id || $device_id || $order_no || $limit['start'] || $limit['end'];
    $tpl_data['pager'] = $pager;
    $tpl_data['orders'] = $orders;
    $tpl_data['accounts'] = $accounts;

    app()->showTemplate('web/order/default', $tpl_data);
} elseif ($op == 'preRefund') {

    $id = request::int('id');

    $order = Order::get($id);
    if (empty($order)) {
        JSON::fail('????????????????????????');
    }

    $data = [
        'id' => $order->getId(),
        'num' => $order->getNum(),
        'price' => number_format($order->getPrice() / 100, 2),
        'orderId' => $order->getOrderId(),
        'createtime' => date('Y-m-d H:i:s', $order->getCreatetime()),
    ];

    if ($order->isPackage()) {
        $data['package'] = $order->getPackageId();
    }

    $pay_result = $order->getExtraData('payResult');
    $data['transaction_id'] = $pay_result['transaction_id'] ?? ($pay_result['uniontid'] ?? $data['orderId']);

    $tpl = [
        'order' => $data,
    ];

    $user = $order->getUser();
    if (!empty($user)) {
        $tpl['user'] = $user->profile();
    }

    $content = app()->fetchTemplate('web/order/refund', $tpl);

    JSON::success([
        'title' => '????????????',
        'content' => $content,
    ]);
} elseif ($op == 'refund') {

    $id = request::int('id');
    if (request::has('num')) {
        $num = request::int('num');

        $res = Order::refund($id, $num, [
            'admin' => _W('username'),
            'ip' => CLIENT_IP,
            'message' => '???????????????',
        ]);
    } elseif (request::has('price')) {
        $price = request::int('price');

        $res = Order::refund2($id, $price, [
            'admin' => _W('username'),
            'ip' => CLIENT_IP,
            'message' => '???????????????',
        ]);
    } else {
        JSON::fail('??????????????????');
    }

    if (is_error($res)) {
        JSON::fail($res);
    }

    JSON::success('???????????????');
} elseif ($op == 'pullsDetail') {

    $id = request::int('id');

    $order = Order::get($id);
    if (empty($order)) {
        JSON::fail('????????????????????????');
    }

    $list = Helper::getOrderPullLog($order);

    $content = app()->fetchTemplate(
        'web/order/pulls',
        [
            'list' => $list,
        ]
    );

    JSON::success(['title' => '????????????', 'content' => $content]);
} elseif ($op == 'commissionDetail') {

    $id = request::int('id');
    $result = Order::getCommissionDetail($id);
    if (is_error($result)) {
        JSON::fail($result);
    }

    $content = app()->fetchTemplate(
        'web/order/detail',
        [
            'list' => $result,
        ]
    );

    JSON::success(['title' => '??????', 'content' => $content]);
} elseif ($op == 'export') {

    $all_headers = Order::getExportHeaders();
    unset($all_headers['ID']);

    $tpl_data['headers'] = $all_headers;
    $tpl_data['s_date'] = (new DateTime('first day of this month'))->format('Y-m-d');
    $tpl_data['e_date'] = (new DateTime())->format('Y-m-d');

    app()->showTemplate('web/order/export', $tpl_data);

} elseif ($op == 'export_list') {

    $res = Order::getExportIDS([
        'agent_openid' => request::str('agent_openid'),
        'account_id' => request::int('accountid'),
        'device_id' => request::int('deviceid'),
        'last_id' => request::int('lastid'),
        'start' => request::str('start'),
        'end' => request::str('end'),
    ]);

    JSON::result($res);

} elseif ($op == 'export_update') {

    $res = Order::export([
        'headers' => request::array('headers'),
        'uid' => request::trim('uid'),
        'ids' => request::array('ids'),
    ]);

    JSON::result($res);

} elseif ($op == 'log') {

    $page = max(1, request::str('page'));
    $page_size = $is_ajax ? 10 : max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

    $query = m('user_logs')->where([
        'level' => [
            LOG_GOODS_PAY,
            LOG_CHARGING_PAY, 
            LOG_RECHARGE,
        ],
    ])->orderBy('id DESC');

    //???????????????id????????????????????????????????????$query->count()???????????????
    //????????????????????????$query->orderBy('id desc'); 
    $last = $query->findOne();
    $total = $last ? $last->getId() : 0;

    if (ceil($total / $page_size) < $page) {
        $page = 1;
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $logs = [];
    /** @var user_logsModelObj $entry */
    foreach ($query->page($page, $page_size)->findAll() as $entry) {
        $log = [
            'id' => $entry->getId(),
            'level' => $entry->getLevel(),
            'orderNO' => $entry->getTitle(),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        $log['data'] = $entry->getData();
        $user = User::get($log['data']['user'], true);
        if ($user) {
            $log['user'] = $user->profile();
        }

        $device = Device::get($log['data']['device']);
        if ($device) {
            $log['device'] = [
                'name' => $device->getName(),
                'id' => $device->getId(),
            ];
        }

        if (empty($log['data']['payResult'])) {
            $log['data']['queryResult'] = Pay::query($log['orderNO']);
        }

        $logs[] = $log;
    }

    $tpl_data['logs'] = $logs;
    $tpl_data['way'] = 'pay';

    app()->showTemplate('web/order/log', $tpl_data);
} elseif ($op == 'stat') {

    //?????? ????????????
    $agent_openid = request::str('agent_openid');
    $device_id = request::int('device_id');

    $date_limit = request::array('datelimit');
    $start = empty($date_limit['start']) ? new DateTime('00:00:00') : DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $date_limit['start'].' 00:00:00'
    );
    $end = empty($date_limit['end']) ? new DateTime() : DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $date_limit['end'].' 00:00:00'
    );

    if (!($start && $end)) {
        Util::itoast('??????????????????', $this->createWebUrl('order', ['op' => 'stat']), 'error');
    }

    $tpl_data['s_date'] = $start->format('Y-m-d');
    $tpl_data['e_date'] = $end->format('Y-m-d');

    $total = [];
    $data = [];

    $end->modify('next day 00:00:00');

    while ($start < $end) {
        $start_ts = $start->getTimestamp();

        $start->modify('+1 day');

        $end_ts = $start->getTimestamp();

        list($t1, $d1) = calc_stats($agent_openid, $device_id, $start_ts, $end_ts);

        foreach ($t1 as $index => $item) {
            $total[$index] += $item;
        }

        foreach ($d1 as $date_str => $item) {
            foreach ($item as $index => $x) {
                $data[$date_str][$index] += $x;
            }
        }
    }

    $tpl_data['open_id'] = $agent_openid;
    $tpl_data['device_id'] = $device_id;
    $tpl_data['data'] = array_reverse($data);
    $tpl_data['total'] = $total;

    app()->showTemplate('web/order/stat', $tpl_data);
}

function calc_stats($agent_openid, $device_id, $start, $end): array
{
    $condition = [
        'createtime >=' => $start,
        'createtime <' => $end,
    ];

    if (!empty($agent_openid)) {
        $agent = Agent::get($agent_openid, true);
        if ($agent) {
            $condition['agent_id'] = $agent->getId();
        } else {
            $condition['agent_id'] = -1;
        }
    }
    if (!empty($device_id)) {
        $condition['device_id'] = $device_id;
    }

    $data = [];
    $total = [
        'income' => 0,
        'refund' => 0,
        'receipt' => 0,
        'wx_income' => 0,
        'wx_refund' => 0,
        'wx_receipt' => 0,
        'ali_income' => 0,
        'ali_refund' => 0,
        'ali_receipt' => 0,
    ];

    $query = Order::query($condition);

    /** @var orderModelObj $item */
    foreach ($query->findAll() as $item) {

        $amount = $item->getPrice();

        $create_date = date('Y-m-d', $item->getCreatetime());

        if (!isset($data[$create_date])) {
            $data[$create_date]['income'] = 0;
            $data[$create_date]['refund'] = 0;
            $data[$create_date]['receipt'] = 0;
            $data[$create_date]['wx_income'] = 0;
            $data[$create_date]['wx_refund'] = 0;
            $data[$create_date]['wx_receipt'] = 0;
            $data[$create_date]['ali_income'] = 0;
            $data[$create_date]['ali_refund'] = 0;
            $data[$create_date]['ali_receipt'] = 0;
        }

        $is_alipay = User::isAliUser($item->getOpenid());

        $data[$create_date]['income'] += $amount;
        $total['income'] += $amount;
        if ($is_alipay) {
            $data[$create_date]['ali_income'] += $amount;
            $total['ali_income'] += $amount;
        } else {
            $data[$create_date]['wx_income'] += $amount;
            $total['wx_income'] += $amount;
        }

        if ($item->getExtraData('refund')) {
            //???????????????
            $data[$create_date]['refund'] += $amount;
            $total['refund'] += $amount;
            if ($is_alipay) {
                $data[$create_date]['ali_refund'] += $amount;
                $total['ali_refund'] += $amount;
            } else {
                $data[$create_date]['wx_refund'] += $amount;
                $total['wx_refund'] += $amount;
            }
        } else {
            $data[$create_date]['receipt'] += $amount;
            $total['receipt'] += $amount;
            if ($is_alipay) {
                $data[$create_date]['ali_receipt'] += $amount;
                $total['ali_receipt'] += $amount;
            } else {
                $data[$create_date]['wx_receipt'] += $amount;
                $total['wx_receipt'] += $amount;
            }
        }
    }

    return [$total, $data];
}
