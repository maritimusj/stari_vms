<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\orderModelObj;

$query = Order::query();

$tpl_data = [
    'commission_balance' => App::isCommissionEnabled(),
];

$agent_openid = Request::str('agent_openid');
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

$device_id = Request::int('device_id');
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

$user_id = Request::int('user_id');
if ($user_id) {
    $user = User::get($user_id);
    if ($user) {
        $query->where($user);

        $tpl_data['s_user_id'] = $user->getId();
        $tpl_data['user_res'] = $user->profile();
    }
}

if (Request::has('account_id')) {
    $account = Account::get(Request::int('account_id'));
    if ($account) {
        $query->where(['account' => $account->getName()]);
    }
}

$way = Request::str('way');
if ($way == 'free') {
    if (App::isFuelingDeviceEnabled()) {
        $query->where(['src' => [Order::FREE, Order::ACCOUNT, Order::FUELING_SOLO]]);
    } else {
        $query->where(['src' => [Order::FREE, Order::ACCOUNT]]);
    }
} elseif ($way == 'pay') {
    if (App::isFuelingDeviceEnabled()) {
        $query->where(['src' => [Order::PAY, Order::FUELING, Order::FUELING_UNPAID]]);
    } else {
        $query->where(['src' => Order::PAY]);
    }
} elseif ($way == 'balance') {
    $query->where(['src' => Order::BALANCE]);
} elseif ($way == 'charging') {
    $query->where(['src' => [Order::CHARGING, Order::CHARGING_UNPAID]]);
} elseif ($way == 'fueling') {
    $query->where(['src' => [Order::FUELING, Order::FUELING_UNPAID, Order::FUELING_SOLO]]);
} elseif ($way == 'refund') {
    $query->where(['refund' => 1]);
    if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
        $query->where(['src' => Order::PAY]);
    }
} elseif ($way == 'unexpected') {
    $query->where(['result_code >' => 0]);
}

$keyword = Request::str('keyword');
if ($keyword) {
    $query->whereOr([
        'nickname LIKE' => "%$keyword%",
        'account LIKE' => "%$keyword%",
    ]);

    $tpl_data['s_keyword'] = $keyword;
}

$order_no = Request::str('order');
if ($order_no) {
    $query->whereOr([
        'order_id LIKE' => "%$order_no%",
        'extra REGEXP' => "\"transaction_id\":\"[0-9]*{$order_no}[0-9]*\"",
    ]);
    $tpl_data['s_order'] = $order_no;
} else {
    $order_no = Request::str('orderNO');
    if ($order_no) {
        $query->where(['order_id' => $order_no]);
        $tpl_data['s_order'] = $order_no;
    }
}

$limit = Request::array('datelimit');
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

$total = $query->count();

$page = max(1, Request::int('page'));
$page_size = Request::is_ajax() ? 10 : Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query->page($page, $page_size);
$query->orderBy('id DESC');

$accounts = [];
$orders = [];

/** @var orderModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = Order::format($entry, true);

    //公众号信息
    $account_name = $entry->getAccount();
    if ($account_name && empty($accounts[$account_name])) {
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
                $profile['questionnaire+1'] = '问卷 ';
                $account = Account::get($entry->getExtraData('ticket.questionnaireAccountId'));
                if ($account) {
                    $profile['questionnaire+1'] .= $account->getTitle();
                } else {
                    $profile['questionnaire+1'] .= 'n/a';
                }
            }
            $accounts[$account_name] = $profile;
        }
    }

    if ($account_name && $accounts[$account_name]) {
        if (isset($accounts[$account_name]['media'])) {
            $data['account_title'] = '视频 '.$accounts[$account_name]['title'];
        } elseif ($accounts[$account_name]['douyin']) {
            $data['account_title'] = '抖音 '.$accounts[$account_name]['title'];
        } elseif ($accounts[$account_name]['wxapp']) {
            $data['account_title'] = '小程序 '.$accounts[$account_name]['title'];
        } elseif ($accounts[$account_name]['questionnaire']) {
            $data['account_title'] = '问卷 '.$accounts[$account_name]['title'];
            $data['questionnaire_log'] = $entry->getExtraData('ticket.logId', '');
        } elseif ($accounts[$account_name]['questionnaire+1']) {
            $data['account_title'] = '公众号 '.$accounts[$account_name]['title'].' + '.$accounts[$account_name]['questionnaire+1'];
            $data['questionnaire_log'] = $entry->getExtraData('ticket.logId', 0);
        } elseif ($accounts[$account_name]['third-party-platform']) {
            $data['account_title'] = '第三方平台 '.$accounts[$account_name]['title'];
        } else {
            $data['account_title'] = '公众号 '.$accounts[$account_name]['title'];
        }
        $data['clr'] = $accounts[$account_name]['clr'];
    } else {
        if ($data['type'] == 'normal') {
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
    }

    //分佣
    $commission = $entry->getExtraData('commission', []);
    if ($commission) {
        $data['commission'] = $commission;
        $data['commission']['fee'] = $entry->getExtraData('pay.fee');
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
        $data['pull_logs'] = $device->isVDevice() || $device->isNormalDevice();
    }

    if ($entry->getExtraData('qrcode')) {
        $data['qrcode'] = true;
    }

    if (App::isGDCVMachineEnabled()) {
        if ($entry->isFree()) {
            $data['cv.upload'] = $entry->getExtraData('CV.upload', [
                'code' => 1,
                'message' => '未知原因',
            ]);
        }
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

if (Request::is_ajax()) {
    $data = [
        'isajax' => true,
        'orders' => $orders,
        'user' => Request::has('user_id'),
        'accounts' => $accounts,
        'pager' => $pager,
    ];

    if (isset($device)) {
        $data['device'] = $device;
    }

    $content = app()->fetchTemplate('web/order/list', $data);

    JSON::success([
        'title' => isset($user) ? '<b>'.$user->getName().'</b>的订单列表' : '',
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