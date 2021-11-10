<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use zovye\Account;
use zovye\Agent;
use zovye\Device;
use zovye\model\deviceModelObj;
use zovye\Goods;
use zovye\model\goods_voucherModelObj;
use zovye\GoodsVoucher;
use zovye\request;
use zovye\model\orderModelObj;
use zovye\State;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\error;
use function zovye\settings;

class order
{

    public static function detail(): array
    {
        $order_id = request::int('orderid');
        $order = \zovye\Order::get($order_id);
        if (empty($order)) {
            return error(State::ERROR, '找不到这个订单!');
        }

        $result = [];
        $user = User::get($order->getOpenid(), true);
        if ($user) {
            $result['user'] = [
                'name' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
            ];
        }
        $device = Device::get($order->getDeviceId());
        if ($device) {
            $result['device'] = [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ];
        }
        if ($order->getPrice() > 0) {
            $m = number_format($order->getPrice() / 100, 2);
            $result['spec'] = "微信支付￥{$m}元购买";
        } elseif ($order->getBalance() > 0) {
            $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
            $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
            $result['spec'] = "使用{$order->getBalance()}{$unit_title}{$balance_title}领取";
        } else {
            $result['spec'] = '免费领取';
        }

        $goods_id = $order->getGoodsId();
        $result['goods'] = Goods::data($goods_id);
        $result['createtime'] = date('Y-m-d H:i:s', $order->getCreatetime());

        return $result;
    }

    public static function default(): array
    {
        $user = common::getAgent();
        $condition = [];

        $guid = request::str('guid');
        if (empty($guid)) {
            $condition['agent_id'] = $user->getAgentId();
        } else {
            $user = \zovye\api\wx\agent::getUserByGUID($guid);
            if (empty($user)) {
                return err('找不到这个用户！');
            }
            $condition['agent_id'] = $user->getAgentId();
        }

        $query = \zovye\Order::query();

        $res = Device::query($condition)->findAll();

        $devices = [];
        $device_keys = [];

        /** @var deviceModelObj $item */
        foreach ($res as $item) {
            $devices[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'imei' => $item->getImei(),
            ];
            $device_keys[] = $item->getId();
        }

        if (request::has('deviceid')) {
            $d_id = request::int('deviceid');
            if (in_array($d_id, $device_keys)) {
                $condition['device_id'] = $d_id;
            } else {
                $condition['device_id'] = -1;
            }
        }

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        if (request::has('start')) {
            $s_date = DateTime::createFromFormat('Y-m-d H:i:s', request::str('start') . ' 00:00:00');
        } else {
            $s_date = new DateTime('first day of this month 00:00:00');
        }

        if (request::has('end')) {
            $e_date = DateTime::createFromFormat('Y-m-d H:i:s', request::str('end') . ' 00:00:00');
            $e_date->modify('next day');
        } else {
            $e_date = new DateTime('first day of next month 00:00:00');
        }

        $condition['createtime >='] = $s_date->getTimestamp();
        $condition['createtime <'] = $e_date->getTimestamp();

        $order_no = request::trim('order');
        if ($order_no) {
            $query->whereOr([
                'order_id LIKE' => "%{$order_no}%",
                'extra REGEXP' => "\"transaction_id\":\"[0-9]*{$order_no}[0-9]*\"",
            ]);
        }

        $way = request::trim('way');
        if ($way == 'free') {
            $condition['price'] = 0;
            $condition['balance'] = 0;
        } elseif ($way == 'fee') {
            $condition['price >'] = 0;
        } elseif ($way == 'balance') {
            $condition['balance >'] = 0;
        } elseif ($way == 'refund') {
            $condition['extra LIKE'] = '%refund%';
        }

        if (request::bool('export')) {
            $t_res = $query->where($condition)->orderBy('id DESC')->findAll();
        } else {
            $query->where($condition);
            $total = $query->count();
            if (ceil($total / $page_size) < $page) {
                $page = 1;
            }
            $t_res = $query->page($page, $page_size)->orderBy('id DESC')->findAll();
        }

        $accounts = [];
        $orders = [];

        /** @var orderModelObj $entry */
        foreach ($t_res as $entry) {
            $character = User::getUserCharacter($entry->getOpenid());
            $data = [
                'id' => $entry->getId(),
                'num' => $entry->getNum(),
                'price' => number_format($entry->getPrice() / 100, 2),
                'balance' => $entry->getBalance(),
                'ip' => $entry->getIp(),
                'account' => $entry->getAccount(),
                'orderId' => $entry->getOrderId(),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'agentId' => $entry->getAgentId(),
                'from' => $character,
            ];

            $data['goods'] = $entry->getExtraData('goods');
            $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

            //用户信息
            $user_openid = $entry->getOpenid();
            $user_obj = User::get($user_openid, true);
            if ($user_obj) {
                $data['user'] = [
                    'id' => $user_obj->getId(),
                    'nickname' => $user_obj->getNickname(),
                    'avatar' => $user_obj->getAvatar(),
                ];
            }

            //设备信息
            $device_id = $entry->getDeviceId();
            $device_obj = Device::get($device_id);
            if ($device_obj) {
                $data['device'] = [
                    'name' => $device_obj->getName(),
                    'id' => $device_obj->getId(),
                ];
            }

            //代理商信息
            $agent_id = $entry->getAgentId();
            $agent = Agent::get($agent_id);
            if ($agent) {
                $level = $agent->getAgentLevel();
                $data['agentId'] = $agent->getId();
                $data['agent'] = [
                    'name' => $agent->getName(),
                    'avatar' => $agent->getAvatar(),
                    'level' => $level,
                ];
            }

            //ip地址信息
            if ($data['ip']) {
                $info = $entry->get('ip_info', []);
                if (empty($info)) {
                    $info = Util::getIpInfo($data['ip']);
                    if ($info) {
                        $entry->set('ip_info', $info);
                    }
                }
                if ($info) {
                    $json = json_decode($info, true);
                    if ($json) {
                        $data['ip_info'] = "{$json['data']['region']}{$json['data']['city']}{$json['data']['district']}";
                    }
                }
            }

            //公众号信息
            if (empty($accounts[$entry->getAccount()])) {
                $account = Account::findOneFromName($entry->getAccount());
                if ($account) {
                    $accounts[$entry->getAccount()] = [
                        'name' => $account->getName(),
                        'clr' => $account->getClr(),
                        'title' => $account->getTitle(),
                        'img' => Util::toMedia($account->getImg()),
                        'qrcode' => Util::toMedia($account->getQrcode()),
                    ];
                }
            }

            $voucher_id = intval($entry->getExtraData('voucher.id'));
            if ($voucher_id > 0) {
                $data['voucher'] = [
                    'id' => $voucher_id,
                    'code' => '&lt;n/a&gt;',
                ];

                /** @var goods_voucherModelObj $v */
                $v = GoodsVoucher::getLogById($voucher_id);
                if ($v) {
                    $data['voucher']['code'] = $v->getCode();
                }
            }

            if ($data['price'] > 0) {
                $data['tips'] = ['text' => '支付', 'class' => 'wxpay'];
            } elseif ($data['balance'] > 0) {
                $data['tips'] = ['text' => '余额', 'class' => 'balancex'];
            } else {
                $data['tips'] = ['text' => '免费', 'class' => 'free'];
            }

            if ($accounts[$data['account']]) {
                $data['clr'] = $accounts[$data['account']]['clr'];
            } else {
                $data['clr'] = $character['color'];
            }

            if ($data['price'] > 0 && $entry->getExtraData('refund')) {
                $time = $entry->getExtraData('refund.createtime');
                $time_formatted = date('Y-m-d H:i:s', $time);
                $data['refund'] = [
                    'title' => "退款时间：{$time_formatted}",
                    'reason' => $entry->getExtraData('refund.message'),
                ];
                $data['clr'] = '#ccc';
            }
            //分佣
            $commission = $entry->getExtraData('commission', []);
            if ($commission) {
                $data['commission'] = $commission;
            }

            $pay_result = $entry->getExtraData('payResult');
            $data['transaction_id'] = isset($pay_result['transaction_id']) ? $pay_result['transaction_id'] : (isset($pay_result['uniontid']) ? $pay_result['uniontid'] : '');

            //出货结果
            $data['result'] = $entry->getExtraData('pull.result', []);
            $orders[] = $data;
        }

        if (request::bool('export')) {
            //
            $headers = [
                '订单ID',  //id
                '订单号',  //orderId  NO
                '支付号',  //transaction_id
                '支付类型', //from -> title
                '用户名',  //user -> nickname
                '商品名称', //goods -> name
                '商品数量', //num
                '商品价格', //goods -> price_formatted
                '购买方式', // price  现金  balance 余额  .. 免费
                '是否退款', // refund exist
                '退款时间', // refund -> title
                '公众号', //
                '代理商名称', //agent  -> name
                '设备名称', //device -> name
                '设备IMEI', //result -> deviceGUID
                'ip地址', //ip
                '创建时间' //createtime
            ];

            $tab_header = implode("\t", $headers);
            $str_export = $tab_header . "\r\n";

            foreach ($orders as $item) {

                $str_export .= $item['id'] . "\t";
                $str_export .= 'NO.' . $item['orderId'] . "\t";
                $str_export .= 'NO.' . $item['transaction_id'] . "\t";
                $str_export .= ($item['from']['title'] ?: '') . "\t";
                $str_export .= ($item['user']['nickname'] ?: '') . "\t";
                $str_export .= ($item['goods']['name'] ?: '') . "\t";
                $str_export .= $item['num'] . "\t";
                $str_export .= ($item['goods']['price_formatted'] ?: '') . "\t";
                if ($item['price'] > 0) {
                    $str_export .= "现金\t";
                } else if ($item['balance'] > 0) {
                    $str_export .= "余额\t";
                } else {
                    $str_export .= "免费\t";
                }

                if (isset($item['refund'])) {
                    $str_export .= "是\t";
                    $str_export .= $item['refund']['title'] . "\t";
                } else {
                    $str_export .= "\t\t";
                }
                if (isset($accounts[$item['account']]['title'])) {
                    $str_export .= $accounts[$item['account']]['title'] . "\t";
                } else {
                    $str_export .= "\t";
                }

                $str_export .= ($item['agent']['name'] ?? '') . "\t";
                $str_export .= ($item['device']['name'] ?? '') . "\t";
                $str_export .= ($item['result']['deviceGUID'] ?? '') . "\t";
                $str_export .= $item['ip'] . "\t";
                $str_export .= $item['createtime'] . "\t";

                $str_export .= "\r\n";
            }

            $file_name = time() . '_' . rand() . '.xls';
            file_put_contents(ATTACHMENT_ROOT . '/' . $file_name, $str_export);

            readfile(ATTACHMENT_ROOT . '/' . $file_name);
            @unlink(ATTACHMENT_ROOT . '/' . $file_name);
            exit;


        } else {
            return [
                'orders' => $orders,
                'accounts' => $accounts,
                'devices' => $devices,
                'page' => $page,
                'pagesize' => $page_size,
                'total' => $total
            ];
        }
    }
}