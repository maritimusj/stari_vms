<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;
use zovye\model\commission_balanceModelObj;
use zovye\model\userModelObj;

$s_user_list = [];

$query = Principal::gspsor();
$s_keyword = Request::trim('keyword', '', true);
if ($s_keyword != '') {
    $query = $query->whereOr([
        'name REGEXP' => $s_keyword,
        'nickname REGEXP' => $s_keyword,
        'mobile REGEXP' => $s_keyword,
    ]);
}

$query->limit(20);

/** @var userModelObj $val */
foreach ($query->findAll() as $val) {
    $s_user_list[] = $val;
}

$date_limit = [
    'start' => Request::str('start'),
    'end' => Request::str('end'),
];

if ($date_limit['start']) {
    $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'].' 00:00:00');
} else {
    $s_date = new DateTime('first day of this month 00:00:00');
}

if ($date_limit['end']) {
    $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'].' 00:00:00');
    $e_date->modify('next day');
} else {
    $e_date = new DateTime('first day of next month 00:00:00');
}

$s_openid = Request::str('agent_openid');
if ($s_openid) {
    $user = User::get($s_openid, true);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', '', 'error');
    }
}

$cond = [
    'createtime >=' => $s_date->getTimestamp(),
    'createtime <' => $e_date->getTimestamp(),
];

//是否导出
if (Request::bool('is_export')) {
    if (empty($user)) {
        Util::itoast('请指定用户！', '', 'error');
    }

    set_time_limit(60);

    //导出
    $logs = [];
    $title_arr = [
        '#',
        '金额',
        '时间',
        '事件',
        '设备',
        '公众号',
    ];

    $file_name = $user->getName().'的数据';
    $query = $user->getCommissionBalance()->log();
    $query->where($cond);
    $query->orderBy('createtime DESC');

    /** @var commission_balanceModelObj $entry */
    foreach ($query->findAll() as $index => $entry) {
        $data = [
            'id' => $index + 1,
            'xval' => number_format($entry->getXVal() / 100, 2, '.', ''),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'event' => '',  //事件
            'device' => '',  //设备
            'wx_account' => '', //公众号
        ];
        if ($entry->getXVal() > 0) {
            $data['xval'] = '+'.$data['xval'];
        }

        if ($entry->getSrc() == CommissionBalance::WITHDRAW) {
            $status = $entry->getState();
            $data['event'] = '佣金提现'.$status;
        } elseif ($entry->getSrc() == CommissionBalance::REFUND) {
            $data['event'] = '退款';
        } elseif (in_array(
            $entry->getSrc(),
            [
                CommissionBalance::ORDER_FREE,
                CommissionBalance::ORDER_BALANCE,
                CommissionBalance::ORDER_WX_PAY,
            ]
        )) {
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            if ($order) {
                $device = Device::get($order->getDeviceId());
                $goods = Goods::data($order->getGoodsId());
                if ($order->getPrice() > 0) {
                    $pay_type = User::getUserCharacter($order->getOpenid())['title'];
                    $spec = $pay_type."：￥".number_format(
                            $order->getPrice() / 100,
                            2
                        )."元 购买：".$goods['name']."x".$order->getNum();
                } elseif ($order->getBalance() > 0) {
                    $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
                    $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
                    $spec = "使用".$order->getBalance(
                        ).$unit_title.$balance_title."购买：".$goods['name']."x".$order->getNum();
                } else {
                    $spec = "免费领取：".$goods['name']."x".$order->getNum();
                }
                $account_name = $order->getAccount();
                if ($account_name) {
                    $data['wx_account'] = $account_name;
                }
                $device_name = $device ? $device->getName() : '未知';
                $data['event'] = $spec;
                $data['device'] = $device_name;
            } else {
                $data['event'] = '未知';
                $data['device'] = '未知';
            }
        } elseif ($entry->getSrc() == CommissionBalance::ORDER_REFUND) {
            $data['event'] = '订单退款，返还佣金';
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            if ($order) {
                $data['event'] .= "，订单号：{$order->getOrderNO()}";
            } else {
                $data['event'] .= "，订单ID：$order_id";
            }
        } elseif ($entry->getSrc() == CommissionBalance::GSP) {
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            if ($order) {
                $device = Device::get($order->getDeviceId());
                $goods = Goods::data($order->getGoodsId());
                if ($order->getPrice() > 0) {
                    $pay_type = User::getUserCharacter($order->getOpenid())['title'];
                    $spec = $pay_type."：￥".number_format(
                            $order->getPrice() / 100,
                            2
                        )."元 购买：".$goods['name']."x".$order->getNum();
                } elseif ($order->getBalance() > 0) {
                    $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
                    $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
                    $spec = "使用".$order->getBalance(
                        ).$unit_title.$balance_title."购买：".$goods['name']."x".$order->getNum();
                } else {
                    $spec = "免费领取：".$goods['name']."x".$order->getNum();
                }
                $account_name = $order->getAccount();
                if ($account_name) {
                    $data['wx_account'] = $account_name;
                }
                $device_name = $device ? $device->getName() : '未知';
                $data['event'] = $spec;
                $data['device'] = $device_name;
            } else {
                $data['event'] = '未知';
                $data['device'] = '未知';
            }
        } elseif ($entry->getSrc() == CommissionBalance::BONUS) {
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            if ($order) {
                $device = Device::get($order->getDeviceId());
                $goods = Goods::data($order->getGoodsId());
                if ($order->getPrice() > 0) {
                    $pay_type = User::getUserCharacter($order->getOpenid())['title'];
                    $spec = $pay_type."：￥".number_format(
                            $order->getPrice() / 100,
                            2
                        )."元 购买：".$goods['name']."x".$order->getNum();
                } elseif ($order->getBalance() > 0) {
                    $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
                    $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
                    $spec = "使用".$order->getBalance(
                        ).$unit_title.$balance_title."购买：".$goods['name']."x".$order->getNum();
                } else {
                    $spec = "免费领取：".$goods['name']."x".$order->getNum();
                }
                $account = $order->getAccount(true);
                if ($account) {
                    $data['wx_account'] = $account->getTitle();
                } else {
                    $data['wx_account'] = $order->getAccount();
                }
                $device_name = $device ? $device->getName() : '未知';
                $data['event'] = $spec;
                $data['device'] = $device_name;
            } else {
                $data['event'] = '未知';
                $data['device'] = '未知';
            }
        } elseif ($entry->getSrc() == CommissionBalance::FEE) {
            $title = '';
            if ($entry->getExtraData('refund')) {
                $title = '（已退回）';
            }
            $data['event'] = '提现手续费'.$title;
        } elseif ($entry->getSrc() == CommissionBalance::ADJUST) {
            $data['event'] = '管理员调整';
        }
        $logs[] = $data;
    }

    Util::exportExcel($file_name, $title_arr, $logs);
}

$title = '';
$logs = [];
$pager = '';
if (!empty($user)) {
    $title = "<b>{$user->getName()}</b>的佣金记录";

    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query = $user->getCommissionBalance()->log();
    $query->where($cond);

    $total = $query->count();

    if ($total > 0) {
        $pager = We7::pagination($total, $page, $page_size);
        $query->page($page, $page_size);
        $query->orderBy('createtime DESC');
        foreach ($query->findAll() as $entry) {
            $logs[] = CommissionBalance::format($entry);
        }
    }
}
$e_date->modify('-1 day');
app()->showTemplate(
    'web/common/commission_export',
    [
        'title' => $title,
        'logs' => $logs,
        'pager' => $pager,
        's_keyword' => $s_keyword,
        's_date' => $s_date->format('Y-m-d'),
        'e_date' => $e_date->format('Y-m-d'),
        's_openid' => $s_openid,
        's_user_list' => $s_user_list,
    ]
);