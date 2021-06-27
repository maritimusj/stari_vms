<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\device_logsModelObj;
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

    $way = request::str('way');
    if ($way == 'free') {
        $query->where(['price' => 0, 'balance' => 0]);
    } elseif ($way == 'fee') {
        $query->where(['price >' => 0]);
    } elseif ($way == 'balance') {
        $query->where(['balance >' => 0]);
    } elseif ($way == 'refund') {
        $query->where(['refund' => 1]);
    } elseif ($way == 'except') {
        $query->where(['result_code >' => 0]);
    } else if ($way == 'sqm') {
        $query->where(['src' => Order::SQM]);
    } elseif ($way == 'aliTicket') {
        $query->where(['src' => Order::ALI_TICKET]);
    }

    $keyword = request::str('keyword');
    if ($keyword) {
        $query->whereOr([
            'nickname LIKE' => "%{$keyword}%",
            'account LIKE' => "%{$keyword}%",
        ]);

        $tpl_data['s_keyword'] = $keyword;
    }

    $order_no = request::str('order');
    if ($order_no) {
        $query->whereOr([
            'order_id LIKE' => "%{$order_no}%",
            'extra REGEXP' => "'\"transaction_id\":\"[0-9]*{$order_no}[0-9]*\"')",
        ]);
        $tpl_data['s_order'] = $order_no;
    }

    $limit = request::array('datelimit');
    if ($limit['start']) {
        $start = DateTime::createFromFormat('Y-m-d H:i:s', $limit['start'] . ' 00:00:00');
        if ($start) {
            $tpl_data['s_start_date'] = $start->format('Y-m-d');
            $query->where(['createtime >=' => $start->getTimestamp()]);
        }
    }

    if ($limit['end']) {
        $end = DateTime::createFromFormat('Y-m-d H:i:s', $limit['end'] . ' 00:00:00');
        if ($end) {
            $tpl_data['s_end_date'] = $end->format('Y-m-d');
            $end->modify('next day');
            $query->where(['createtime <' => $end->getTimestamp()]);
        }
    }

    $page = max(1, request::int('page'));
    $page_size = $is_ajax ? 10 : request::int('pagesize', DEFAULT_PAGESIZE);

    $total = $query->count();
    if (ceil($total / $page_size) < $page) {
        $page = 1;
    }

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    $pager = We7::pagination($total, $page, $page_size);
    if (stripos($pager, '&filter=1') === false) {
        $filter = [
            'agent_openid' => $agent_openid,
            'user_id' => $user_id,
            'device_id' => $device_id,
            'order' => $order_no,
            'datelimit[start]' => $start ? $start->format('Y-m-d') : '',
            'datelimit[end]' => $end ? $end->format('Y-m-d') : '',
            'filter' => 1,
        ];

        foreach ($filter as $index => $entry) {
            if (empty($entry)) {
                unset($filter[$index]);
            }
        }
        $params_str = http_build_query($filter);
        $pager = preg_replace('#href="(.*?)"#', 'href="${1}&' . $params_str . '"', $pager);
    }

    $accounts = [];
    $orders = [];

    /** @var orderModelObj $entry */
    foreach ($query->findAll() as $entry) {
        //公众号信息
        if (empty($accounts[$entry->getAccount()])) {
            $account = Account::findOne(['name' => $entry->getAccount()]);
            if ($account) {
                $profile = [
                    'name' => $account->getName(),
                    'clr' => $account->getClr(),
                    'title' => $account->getTitle(),
                    'img' => $account->getImg(),
                ];
                if ($account->isVideo()) {
                    $profile['media'] = $account->getMedia();
                } else {
                    $profile['qrcode'] = $account->getQrcode();
                }
                $accounts[$entry->getAccount()] = $profile;
            }
        }

        $data = Order::format($entry, true);
        if ($accounts[$data['account']]) {
            if (isset($accounts[$data['account']]['media'])) {
                $data['account_title'] = '视频 ' . $data['account'];
            } else {
                $data['account_title'] = '公众号 ' . $data['account'];
            }

            $data['clr'] = $accounts[$data['account']]['clr'];
        } else {
            $data['account_title'] = 'n/a';
            $data['clr'] = '#ccc';

            if ($data['refund']) {
                $data['clr'] = '#ccc';
            } else {
                $data['clr'] = $data['from']['color'];
            }
        }

        //分佣
        $commission = $entry->getExtraData('commission', []);
        if ($commission) {
            $data['commission'] = $commission;
        }

        $pay_result = $entry->getExtraData('payResult');
        if ($pay_result) {
            $data['transaction_id'] = isset($pay_result['transaction_id']) ? $pay_result['transaction_id'] : (isset($pay_result['uniontid']) ? $pay_result['uniontid'] : $data['orderId']);
            $data['src'] = strval($pay_result['from']);
            $data['pay_name'] = strval($pay_result['type']);
        }

        if ($entry->getBluetoothDeviceBUID()) {
            $data['result'] = $entry->isBluetoothResultFail() ? ['errno' => 1] : [];
        } else {
            $data['result'] = $entry->getExtraData('pull.result', []);
        }
        $device = $entry->getDevice();
        if ($device) {
            $data['pull_logs'] = !$device->isVDevice() && !$device->isBlueToothDevice() ? true : '没有出货记录';
        }
        $orders[] = $data;
    }

    if ($is_ajax) {
        $data = [
            'isajax' => true,
            'orders' => $orders,
            'accounts' => $accounts,
            'pager' => $pager,
        ];

        if (isset($device)) {
            $data['device'] = $device;
        }

        $content = app()->fetchTemplate('web/order/list', $data);

        JSON::success([
            'title' => '',
            'content' => $content,
        ]);
    }

    $tpl_data['s_way'] = $way;
    $tpl_data['backer'] = $keyword || $agent_openid || $user_id || $device_id || $order_no || $limit['start'] || $limit['end'];
    $tpl_data['pager'] = $pager;
    $tpl_data['orders'] = $orders;
    $tpl_data['accounts'] = $accounts;

    app()->showTemplate('web/order/default', $tpl_data);
} elseif ($op == 'preRefund') {

    $id = request::int('id');

    $order = Order::get($id);
    if (empty($order)) {
        JSON::fail('找不到这个订单！');
    }

    $data = [
        'id' => $order->getId(),
        'num' => $order->getNum(),
        'price' => number_format($order->getPrice() / 100, 2),
        'orderId' => $order->getOrderId(),
        'createtime' => date('Y-m-d H:i:s', $order->getCreatetime()),
    ];

    $data['goods'] = $order->getExtraData('goods');
    $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

    $pay_result = $order->getExtraData('payResult');
    $data['transaction_id'] = isset($pay_result['transaction_id']) ? $pay_result['transaction_id'] : (isset($pay_result['uniontid']) ? $pay_result['uniontid'] : $data['orderId']);

    $tpl = [
        'order' => $data,
    ];

    $user = $order->getUser();
    if (!empty($user)) {
        $tpl['user'] = $user->profile();
    }

    $content = app()->fetchTemplate('web/order/refund', $tpl);

    JSON::success([
        'title' => '订单退款',
        'content' => $content,
    ]);
} elseif ($op == 'refund') {

    $id = request::int('id');
    $num = request::int('num');

    $res = Order::refund($id, $num, [
        'admin' => _W('username'),
        'ip' => CLIENT_IP,
        'message' => '管理员退款',
    ]);

    if (is_error($res)) {
        JSON::fail($res);
    }

    JSON::success('退款成功！');
} elseif ($op == 'pullsDetail') {

    $id = request::int('id');

    $order = Order::get($id);
    if (empty($order)) {
        JSON::fail('找不到这个订单！');
    }

    $condition = We7::uniacid([
        'createtime >=' => $order->getCreatetime(),
        'createtime <' => $order->getCreatetime() + 3600,
        'data REGEXP' => "s:5:\"order\";i:{$order->getId()};",
    ]);

    $device = $order->getDevice();
    if ($device) {
        $condition['title'] = $device->getImei();
    }

    $query = m('device_logs')->where($condition);

    $list = [];
    /** @var device_logsModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'imei' => $entry->getTitle(),
            'title' => Device::formatPullTitle($entry->getLevel()),
            'goods' => $entry->getData('goods'),
            'user' => $entry->getData('user'),
        ];

        $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

        $result = $entry->getData('result');
        if (is_array($result)) {
            if (isset($result['errno'])) {
                $data['result'] = [
                    'errno' => intval($result['errno']),
                    'message' => $result['message'],
                ];
            } elseif (isset($result['data']['errno'])) {
                $data['result'] = [
                    'errno' => intval($result['data']['errno']),
                    'message' => $result['data']['message'],
                ];
            } else {
                $data['result'] = [
                    'errno' => -1,
                    'message' => '<未知>',
                ];
            }
        } else {
            $data['result'] = [
                'errno' => empty($result),
                'message' => empty($result) ? '失败' : '成功',
            ];
        }

        $list[] = $data;
    }

    $content = app()->fetchTemplate(
        'web/order/pulls',
        [
            'list' => $list,
        ]
    );

    JSON::success(['title' => '出货记录', 'content' => $content]);
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

    JSON::success(['title' => '详情', 'content' => $content]);
} elseif ($op == 'export') {

    $all_headers = getHeaders();    
    unset($all_headers['#'], $all_headers['ID']);

    $tpl_data['headers'] = $all_headers;
    $tpl_data['s_date'] = (new DateTime('first day of this month'))->format('Y-m-d');
    $tpl_data['e_date'] = (new DateTime())->format('Y-m-d');

    app()->showTemplate('web/order/export', $tpl_data);

} elseif ($op == 'export_list') {

    $agent_openid = request::str('agent_openid');
    $account_id = request::int('accountid');
    $device_id = request::int('deviceid');
    $last_id = request::int('lastid');

    $query = Order::query();
    if ($agent_openid) {
        $agent = User::get($agent_openid, true);
        if (empty($agent)) {
            Util::itoast('找不到指定的代理商！', Util::url('order', ['op' => 'export']), 'error');
        }
        $query->where(['agent_id' => $agent->getId()]);
    }

    if ($account_id) {
        $account = Account::get($account_id);
        if (empty($account)) {
            Util::itoast('找不到指定的公众号！', Util::url('order', ['op' => 'export']), 'error');
        }
        $query->where(['account' => $account->getName()]);
    }

    if ($device_id) {
        $device = Device::get($device_id);
        if (empty($device)) {
            Util::itoast('找不到指定的设备！', Util::url('order', ['op' => 'export']), 'error');
        }
        $query->where(['device_id' => $device->getId()]);
    }

    $date_start = request::str('start');
    if ($date_start) {
        $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_start . ' 00:00:00');
    }

    if (empty($s_date)) {
        $s_date = new DateTime('first day of this month 00:00:00');
    }

    $date_end = request::str('end');
    if ($date_end) {
        $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_end . ' 00:00:00');
    }
    if (empty($e_date)) {
        $e_date = new DateTime();
    }

    $e_date = $e_date->modify('next day 00:00:00');

    $query->where([
        'createtime >=' => $s_date->getTimestamp(),
        'createtime <' => $e_date->getTimestamp(),
    ]);

    if ($last_id > 0) {
        $query->where(['id >' => $last_id]);
    }

    $query->orderBy('id ASC');
    $query->limit(1000);

    $result = $query->findAll([], true);
    $total = $result->count();

    $ids = [];
    for($i = 0; $i < $total; $i ++) {
        $ids[] = $result[$i]['id'];
    }

    JSON::success($ids);

} elseif ($op == 'export_update') {
    
    $headers = request::array('headers');
    if (empty($headers)) {
        $headers = ['order_no', 'createtime'];
    } 

    array_unshift($headers, 'ID');
    array_unshift($headers, '#');

    $uid = request::trim('uid');
    $ids = request::array('ids');

    $query = Order::query(['id' => $ids]);
    $query->orderBy('id ASC');

    $result = [];

    /** @var orderModelObj $entry */
    foreach ($query->findAll() as $index => $entry) {

        $user = User::get($entry->getOpenid(), true);
        $goods = Goods::data($entry->getGoodsId());
        $device = Device::get($entry->getDeviceId());

        $data = [];

        foreach ($headers as $header) {
            switch ($header) {
                case '#':
                    $data[$header] = $index + 1;
                    break;
                case 'ID':
                    $data[$header] = $entry->getId();
                    break;
                case 'order_no':
                    $data[$header] = 'NO.' . $entry->getOrderId();
                    break;
                case 'pay_no':
                    $pay_result = $entry->getExtraData('payResult');
                    if ($pay_result) {
                        if (isset($pay_result['uniontid'])) {
                            $data[$header] = $pay_result['uniontid'];
                        } elseif (isset($pay_result['transaction_id'])) {
                            $data[$header] = $pay_result['transaction_id'];
                        } else {
                            $data[$header] = '';
                        }
                    } else {
                        $data[$header] = '';
                    }
                    break;
                case 'pay_type':
                    $data[$header] = User::getUserCharacter($entry->getUser())['title'];
                    break;
                case 'openid':
                    $data[$header] = $user ? $user->getOpenid() : '';
                    break;
                case 'username':
                    $data[$header] = $user ? str_replace('"', '', $user->getName()) : '';
                    break;
                case 'sex':
                    if ($user) {
                        $profile = $user->profile();
                        $data[$header] = $profile['sex'] == 1 ? '男' : ($profile['sex'] == 2 ? '女' : '未知');
                    } else {
                        $data[$header] = '';
                    }
                    break;
                case 'region':
                    if ($user) {
                        $profile = $user->profile();
                        $data[$header] = "{$profile['country']} {$profile['province']} {$profile['city']}";
                    } else {
                        $data[$header] = '';
                    }
                    break;
                case 'ip':
                    $data[$header] = $entry->getIp();
                    break;
                case 'address':
                    $info = $entry->get('ip_info', []);
                    if ($info) {
                        $json = json_decode($info, true);
                        if ($json) {
                            $data[$header] = "{$json['data']['region']}{$json['data']['city']}{$json['data']['district']}";
                        } else {
                            $data[$header] = '';
                        }
                    } else {
                        $data[$header] = '';
                    }
                    break;
                case 'goods_name':
                    $data[$header] = str_replace('"', '', $goods['name']);
                    break;
                case 'goods_num':
                    $data[$header] = $entry->getNum();
                    break;
                case 'goods_price':
                    $data[$header] = number_format($entry->getPrice() / 100, 2);
                    break;
                case 'way':
                    if ($entry->getPrice() > 0) {
                        $data[$header] = '现金';
                    } elseif ($entry->getBalance() > 0) {
                        $data[$header] = '余额';
                    } else {
                        $data[$header] = '免费';
                    }
                    break;
                case 'refund':
                    if ($entry->getPrice() > 0 && $entry->getExtraData('refund')) {
                        $data[$header] = '已退款';
                    } else {
                        $data[$header] = '';
                    }
                    break;
                case 'refund_time':
                    if ($entry->getPrice() > 0 && $entry->getExtraData('refund')) {
                        $time = $entry->getExtraData('refund.createtime');
                        $data[$header] = date('Y-m-d H:i:s', $time);
                    } else {
                        $data[$header] = '';
                    }
                    break;
                case 'account_title':
                    $account = Account::findOne(['name' => $entry->getAccount()]);
                    $title = $account ? $account->getTitle() : $entry->getAccount();
                    $data[$header] = str_replace('"', '', $title);
                    break;
                case 'agent':
                    $agent = Agent::get($entry->getAgentId());
                    $name = $agent ? $agent->getName() : $entry->getExtraData('agent.name', '');
                    $data[$header] = str_replace('"', '', $name);
                    break;
                case 'device_title':
                    $data[$header] = $device ? str_replace('"', '', $device->getName()) : '';
                    break;
                case 'device_imei':
                    $data[$header] = $device ? $device->getImei() : $entry->getExtraData('device.imei', '');
                    break;
                case 'createtime':
                    $data[$header] = date('Y-m-d H:i:s', $entry->getCreatetime());
                    break;
                default:
                    $data[$header] = '';
            }
        }
        $result[] = $data;
    }

    $all_headers = getHeaders();
    $column = array_values(array_intersect_key($all_headers, array_flip($headers)));  
    $filename =  "export/{$uid}.xls";

    $locker = Locker::try($uid, 30);
    if ($locker) {
        Util::exportExcelFile(ATTACHMENT_ROOT . $filename, $column, $result);
    } else {
        Util::logToFile('export', [
            'error' => 'lock failed',
            'uid' => $uid,
        ]);
    }

    JSON::success([
        'filename' => Util::toMedia($filename),
    ]);

} elseif ($op == 'log') {

    $page = max(1, request::str('page'));
    $page_size = $is_ajax ? 10 : max(1, request::int('pagesize', DEFAULT_PAGESIZE));

    $query = m('user_logs')->where(['level' => LOG_PAY])->orderBy('id DESC');

    //使用自增长id做为总数，数据量大时使用$query->count()效率太低，
    //注意：需要先调用$query->orderBy('id desc'); 
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

    //统计 订单金额
    $agent_openid = request::str('agent_openid');
    $device_id = request::int('device_id');

    $date_limit = request::array('datelimit');
    $start = empty($date_limit['start']) ? new DateTime('00:00:00') : DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'] . ' 00:00:00');
    $end = empty($date_limit['end']) ? new DateTime() : DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'] . ' 00:00:00');

    if (!($start && $end)) {
        Util::itoast('时间不正确！', $this->createWebUrl('order', ['op' => 'stat']), 'error');
    }

    $tpl_data['s_date'] = $start->format('Y-m-d');
    $tpl_data['e_date'] = $end->format('Y-m-d');

    $total = [];
    $data = [];

    $end->modify('next day 00:00:00');

    while ($start < $end) {
        $start_ts = $start->getTimestamp();

        $start->modify('+1 day');

        if ($start > $end) {
            $start = $end;
        }

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

/**
 * @return string[]
 */
function getHeaders(): array
{
    return [
        '#' => '#',
        'ID' => 'ID',
        'order_no' => '订单号',
        'pay_no' => '支付号',
        'pay_type' => '支付类型',
        'username' => '用户名',
        'openid' => '用户openid',
        'sex' => '用户性别',
        'region' => '用户区域',
        'goods_name' => '商品名称',
        'goods_num' => '商品数量',
        'goods_price' => '商品价格',
        'way' => '购买方式',
        'refund' => '是否退款',
        'refund_time' => '退款时间',
        'account_title' => '公众号',
        'agent' => '代理商名称',
        'device_title' => '设备名称',
        'device_imei' => '设备IMEI',
        'ip' => 'ip地址',
        'address' => '定位地址',
        'createtime' => '创建时间',
    ];
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
            //如果是退款
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
