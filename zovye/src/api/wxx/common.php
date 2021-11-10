<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxx;

use ali\aop\AopClient;
use ali\aop\request\AlipaySystemOauthTokenRequest;
use DateTime;
use Exception;
use zovye\Account;
use zovye\Advertising;
use zovye\Agent;
use zovye\App;
use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Device;
use zovye\model\deviceModelObj;
use zovye\GoodsVoucher;
use zovye\Helper;
use zovye\request;
use zovye\Job;
use zovye\JSON;
use zovye\LoginData;
use zovye\model\goods_voucher_logsModelObj;
use zovye\Order;
use zovye\model\orderModelObj;
use zovye\Pay;
use zovye\State;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\We7;
use function zovye\err;
use function zovye\error;
use function zovye\request;
use function zovye\is_error;
use function zovye\m;
use function zovye\settings;

class common
{
    /**
     * 用户登录，小程序必须提交code,encryptedData和iv值
     *
     * @return array
     */
    public static function login(): array
    {
        $res = \zovye\api\wx\common::getDecryptedWxUserData();
        if (is_error($res)) {
            Util::logToFile('wxapi', $res);
            return error(State::ERROR, '用户登录失败，请稍后再试！[103]');
        }

        return self::doUserLogin($res);
    }

    public static function getDeviceInfo(): array
    {
        $imei = request::trim('imei');
        $res = Device::get($imei, true);
        if (empty($res)) {
            return error(State::ERROR, '没有数据！');
        }

        $data = [
            'id' => $res->getId(),
            'name' => $res->getName(),
            'mobile' => ''
        ];
        $agent = $res->getAgent();
        if ($agent) {
            $data['mobile'] = $agent->getMobile();
        }
        return ['data' => $data];
    }

    /**
     * 获取设备相关的设置
     * @return array
     */
    public static function pageInfo(): array
    {
        $imei = request::str('device');

        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        $result = Util::getTplData();
        if ($device->isBlueToothDevice()) {
            $extra = $device->get('extra', []);
            $result['device'] = [
                'buid' => $device->getBUID(),
                'mac' => $device->getMAC(),
                'is_down' => isset($extra['isDown']) && $extra['isDown'] ? 1 : 0,
            ];
        }
        $agent = $device->getAgent();
        if ($agent) {
            if ($agent->settings('agentData.misc.siteTitle') || $agent->settings('agentData.misc.siteLogo'))
                $result['agent'] = [
                    'title' => $agent->settings('agentData.misc.siteTitle'),
                    'logo' => Util::toMedia($agent->settings('agentData.misc.siteLogo'))
                ];
        }

        return $result;
    }

    /**
     * 获取设备相关的广告
     * @return array
     */
    public static function advs(): array
    {
        $imei = request::str('device');

        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        //广告列表
        $advs = $device->getAdvs(Advertising::WELCOME_PAGE);
        $result = [];
        foreach ($advs as $adv) {
            if ($adv['extra']['images']) {
                foreach ($adv['extra']['images'] as $image) {
                    if ($image) {
                        $result[] = [
                            'id' => intval($adv['id']),
                            'name' => strval($adv['name']),
                            'image' => strval(Util::toMedia($image)),
                            'link' => strval($adv['extra']['link']),
                            'app_id' => $adv['extra']['app_id'],
                            'app_path' => $adv['extra']['app_path'],
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 手机小程序蓝牙设备连接成功
     * @return array
     */
    public static function onConnected(): array
    {
        $imei = request::str('device');
        $data = request('data');

        /** @var deviceModelObj $device */
        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return error(State::ERROR, '不是蓝牙设备！');
        }

        $proto = $device->getBlueToothProtocol();
        if (empty($proto)) {
            return error(State::ERROR, '无法加载蓝牙协议！');
        }

        $device->setBluetoothStatus(Device::BLUETOOTH_CONNECTED);
        $device->setLastOnline(TIMESTAMP);
        $device->save();

        $cmd = $proto->onConnected($device->getBUID(), $data);
        if ($cmd) {
            Device::createBluetoothCmdLog($device, $cmd);
            return [
                'data' => $cmd->getEncoded(IBlueToothProtocol::BASE64),
                'hex' => $cmd->getEncoded(IBlueToothProtocol::HEX),
            ];
        }

        return error(State::ERROR, '无法获取指令！');
    }

    public static function deviceStatus(): array
    {
        $imei = request::str('device');

        /** @var deviceModelObj $device */
        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return error(State::ERROR, '不是蓝牙设备！');
        }

        $proto = $device->getBlueToothProtocol();
        if (empty($proto)) {
            return error(State::ERROR, '无法加载蓝牙协议！');
        }

        if ($device->isLowBattery()) {
            return error(State::ERROR, '设备电量低，暂时无法购买！');
        }

        if ($device->isBluetoothReady()) {
            return [
                'ready' => true,
            ];
        }

        $cmd = $proto->initialize($device->getBUID());
        if (is_error($cmd)) {
            return $cmd;
        }

        if (empty($cmd)) {
            return [
                'ready' => true,
            ];
        }

        Device::createBluetoothCmdLog($device, $cmd);

        return [
            'data' => $cmd->getEncoded(IBlueToothProtocol::BASE64),
            'hex' => $cmd->getEncoded(IBlueToothProtocol::HEX),
        ];
    }

    /**
     * 收到蓝牙设备的数据
     * @return array
     */
    public static function onDeviceData(): array
    {
        $imei = request::str('device');
        $data = request('data');

        /** @var deviceModelObj $device */
        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return error(State::ERROR, '不是蓝牙设备！');
        }

        $proto = $device->getBlueToothProtocol();
        if (empty($proto)) {
            return error(State::ERROR, '无法加载蓝牙协议！');
        }

        $result = $proto->parseMessage($device->getBUID(), $data);
        if (empty($result)) {
            return error(State::ERROR, '无法解析消息！');
        }

        if ($result->isOpenResultOk() || $result->isOpenResultFail()) {

            $order = Order::getLastOrderOfDevice($device);

            if ($order && empty($order->getExtraData('bluetooth.raw'))) {

                $order->setExtraData('bluetooth.raw', $result->getRawData());

                if ($result->isOpenResultOk()) {
                    $order->setBluetoothResultOk();
                } elseif ($result->isOpenResultFail()) {
                    $order->setBluetoothResultFail($result->getMessage());
                    if (Helper::NeedAutoRefund($device)) {
                        //启动退款
                        Job::refund($order->getOrderNO(), $result->getMessage());
                    }
                }

                $order->save();
            }

            if ($result->isOpenResultFail()) {
                $code = intval($result->getCode());
                $message = strval($result->getMessage());
                $device->setError($code, $message);
                $device->scheduleErrorNotifyJob($code, $message);
            } else {
                $device->cleanError();
            }
        }

        if ($result->isReady()) {
            $device->setBluetoothStatus(Device::BLUETOOTH_READY);
        }

        Device::createBluetoothEventLog($device, $result);

        $data = [
            'data' => null,
        ];

        $battery = $result->getBatteryValue();

        if ($battery != -1) {
            $device->setQoe($battery);
            if ($device->isLowBattery()) {
                $device->setError(Device::ERROR_LOW_BATTERY, Device::desc(Device::ERROR_LOW_BATTERY));
                $device->scheduleErrorNotifyJob(Device::ERROR_LOW_BATTERY, Device::desc(Device::ERROR_LOW_BATTERY));
            }
            if ($battery >= 0) {
                $data['battery'] = $battery;
            }
        }

        $device->save();

        $cmd = $result->getCmd();
        if ($cmd) {
            Device::createBluetoothCmdLog($device, $cmd);

            $data['data'] = $cmd->getEncoded(IBlueToothProtocol::BASE64);
            $data['hex'] = $cmd->getEncoded(IBlueToothProtocol::HEX);
        }

        return $data;
    }

    /**
     * 取货码 出货
     * @return array
     */
    public static function voucherGet(): array
    {
        $imei = request::str('device');
        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        $goods_id = request::int('goodsId');
        $code = request::str('code');

        /** @var goods_voucher_logsModelObj $v */
        $v = GoodsVoucher::getLogByCode($code);
        if (empty($v)) {
            return error(State::ERROR, '取货码不存在！');
        }

        if (!$v->isValid()) {
            return error(State::ERROR, '无效的取货码!');
        }

        if ($v->getGoodsId() != $goods_id) {
            return error(State::ERROR, '无法领取这个商品！');
        }

        $user = self::getUser();
        if ($device->isBlueToothDevice()) {
            try {
                $result = Util::openDevice(['level' => LOG_GOODS_VOUCHER, $device, $user, $v, 'goodsId' => $goods_id, 'online' => false]);
            } catch (Exception $e) {
                return error(State::ERROR, $e->getMessage());
            }

            if (is_error($result)) {
                return $result;
            }

            $order = Order::get($result['orderid']);
            if (empty($order)) {
                return error(State::ERROR, '出货失败：找不到订单！');
            }

            //设置蓝牙出货标专为0，表示出货结果未确认!
            $order->setExtraData('bluetooth', [
                'result' => 0,
                'deviceBUID' => $device->getBUID(),
            ]);

            if (!$order->save()) {
                return error(State::ERROR, '出货失败：无法保存订单数据！');
            }

            return [
                'msg' => $result['msg'],
                'data' => $result['result'],
            ];
        }
        return error(State::ERROR, '出货失败：不是蓝牙主板！');
    }

    /**
     * 创建支付订单
     */
    public static function orderCreate(): array
    {
        $user = self::getUser();

        App::setContainer($user);

        $imei = request::str('device');
        $goods_id = request::int('goodsId');

        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return error(State::ERROR, '不是蓝牙设备！');
        }

        $goods = $device->getGoods($goods_id);
        if (empty($goods) || empty($goods['allowPay']) || $goods['price'] <= 0) {
            return error(State::ERROR, '无法购买这个商品，请联系管理员！');
        }

        if ($goods['num'] <= 0) {
            return error(State::ERROR, '对不起，您来晚了！');
        }

        $discount = User::getUserDiscount($user, $goods);
        $goods['price'] -= $discount;

        list($order_no, $data) = Pay::createXAppPay($device, $user, $goods, [
            'level' => LOG_GOODS_PAY,
            'discount' => $discount,
        ]);

        if (is_error($data)) {
            return error(State::ERROR, '创建支付失败: ' . $data['message']);
        }

        //加入一个支付结果检查
        Job::orderPayResult($order_no);

        //加入一个支付超时任务
        $res = Job::orderTimeout($order_no);
        if (empty($res) || is_error($res)) {
            return error(State::ERROR, '创建支付超时任务失败！');
        }

        $data['orderNO'] = $order_no;
        return $data;
    }

    public static function orderGet(): array
    {
        $imei = request::str('device');
        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        $order_no = request::str('orderNO');

        $order = Order::getLastOrderOfDevice($device);
        if (empty($order)) {
            return error(State::ERROR, '没有订单！');
        }

        if ($order->getOrderNO() != $order_no) {
            return error(State::ERROR, '订单号不匹配！');
        }

        if ($order->isBluetoothResultOk()) {
            return error(State::ERROR, '订单已成功！');
        }

        if ($order->isBluetoothResultFail()) {
            return error(State::ERROR, '订单已失败！');
        }

        $data = $order->getExtraData('pull.result', '');
        if (empty($data)) {
            return error(State::ERROR, '出货加密凭证为空，请联系管理员！');
        }

        return [
            'data' => $data,
            'hex' => bin2hex(base64_decode($data)),
        ];
    }

    /**
     * 查询订单状态
     * @return array
     */
    public static function orderStats(): array
    {
        $imei = request::str('device');
        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return error(State::ERROR, '不是蓝牙设备！');
        }

        $order = Order::getLastOrderOfDevice($device);
        if (empty($order)) {
            return error(State::ERROR, '没有找到订单！');
        }

        if ($order->getBluetoothDeviceBUID() !== $device->getBUID()) {
            return error(State::ERROR, '订单与设备不匹配！');
        }

        /**
         * result = 0 表示进行中
         * result = 1 表示成功
         * result = 2 表示失败
         */
        $result = 0;
        if ($order->isBluetoothResultOk()) {
            $result = 1;
        } elseif ($order->isBluetoothResultFail()) {
            $result = 2;
        }

        $result = [
            'uid' => $order->getOrderNO(),
            'result' => $result,
        ];

        $vouchers = $order->getExtraData('extra.voucher.recv', 0);
        if ($vouchers > 0) {
            $result['tips'] = [
                'type' => 'info',
                'msg' => "恭喜你获取{$vouchers}张提货券，详情请到个人中心查看！",
            ];
        }

        return $result;
    }

    public static function FBPic(): array
    {
        We7::load()->func('file');
        $res = We7::file_upload($_FILES['pic'], 'image');

        if (!is_error($res)) {
            $filename = $res['path'];
            if ($res['success'] && $filename) {
                try {
                    We7::file_remote_upload($filename);
                } catch (Exception $e) {
                    return error(State::ERROR, $e->getMessage());
                }
            }
            $url = $filename;
            return ['data' => $url];
        }

        return error(State::ERROR, '上传失败！');
    }

    public static function feedback(): array
    {
        $user = self::getUser();

        $device_id = request('device');

        $text = request('text');
        $pics = request('pics');

        $device = Device::get($device_id, true);
        $data = [
            'device_id' => $device->getId(),
            'user_id' => $user->getId(),
            'text' => $text,
            'pics' => serialize($pics),
            'createtime' => time(),

        ];

        if (m('device_feedback')->create($data)) {
            return ['msg' => '反馈成功！'];
        }

        return error(State::ERROR, '反馈失败！');
    }

    public static function deviceAdvs(): array
    {
        $type = request::int('typeid');
        $num = empty(request('num')) ? 10 : request::int('num');

        return Util::getDeviceAdvs(request('deviceid'), $type, $num);
    }

    public static function orderDefault(): array
    {
        $user = self::getUser();

        $query = Order::query();
        $condition = [];

        $agent = $user->getAgent();
        $condition['agent_id'] = $agent->getId();

        $res = Device::query(We7::uniacid(['agent_id' => $agent->getId()]))->findAll();
        $devices = [];
        $device_keys = [];
        /** @var deviceModelObj $item */
        foreach ($res as $item) {
            $devices[$item->getId()] = $item->getName() . ' - ' . $item->getImei();
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

        $order_no = request::trim('order');
        if ($order_no) {
            $condition['order_id LIKE'] = '%' . $order_no . '%';
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

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $query->where($condition);
        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $accounts = [];
        $orders = [];
        /** @var orderModelObj $entry */
        foreach ($query->page($page, $page_size)->orderBy('id DESC')->findAll() as $entry) {
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
                        'img' => $account->getImg(),
                        'qrcode' => $account->getQrcode(),
                    ];
                }
            }

            $voucher_id = intval($entry->getExtraData('voucher.id'));
            if ($voucher_id > 0) {
                $data['voucher'] = [
                    'id' => $voucher_id,
                    'code' => '&lt;n/a&gt;',
                ];
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
                    'title' => "退款时间：$time_formatted",
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
            $data['transaction_id'] = $pay_result['transaction_id'] ?? ($pay_result['uniontid'] ?? $data['orderId']);

            //出货结果
            $data['result'] = $entry->getExtraData('pull.result', []);
            $orders[] = $data;
        }

        return [
            'orders' => $orders,
            'accounts' => $accounts,
            'devices' => $devices,
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total
        ];
    }

    public static function homepageDefault(): array
    {
        $user = self::getUser();

        $condition = [];
        $agent = $user->getAgent();
        $condition['agent_id'] = $agent->getId();

        $device_stat = [
            'all' => 0,
            'on' => 0,
            'off' => 0,
        ];

        $time_less_15 = new DateTime('-15 min');
        $power_time = $time_less_15->getTimestamp();
        $device_stat['all'] = Device::query($condition)->count();
        $device_stat['on'] = Device::query('last_ping IS NOT NULL AND last_ping > ' . $power_time)->count();
        $device_stat['off'] = $device_stat['all'] - $device_stat['on'];

        $data = [
            'all' => [
                'n' => 0, //全部交易数量
            ],
            'today' => [
                'n' => 0, //今日交易数量,
            ],
            'yesterday' => [
                'n' => 0, //昨日交易数量,
            ],
            'last7days' => [
                'n' => 0, //近7日交易数量
            ],
            'month' => [
                'n' => 0, //本月交易数量
            ],
            'lastmonth' => [
                'n' => 0, //上月交易数量,
            ],
        ];

        $date = new DateTime();
        $date->modify('today');
        $today_timestamp = $date->getTimestamp();
        $date->modify('yesterday');
        $yesterday_timestamp = $date->getTimestamp();
        $date->modify('today');
        $date->modify('tomorrow');
        $tomorrow_timestamp = $date->getTimestamp();
        $date->modify('today');
        $date->modify('+7 days');
        $last7days_timestamp = $date->getTimestamp();
        $date->modify('today');
        $date->modify('first day of last month');
        $fl_mon_timestamp = $date->getTimestamp();
        $date->modify('today');
        $date->modify('first day of this month');
        $ft_mon_timestamp = $date->getTimestamp();

        $data['all']['n'] = Order::query($condition)->count();
        $data['today']['n'] = Order::query($condition)
            ->where(['createtime >=' => $today_timestamp, 'createtime <' => $tomorrow_timestamp])
            ->count();
        $data['yesterday']['n'] = Order::query($condition)
            ->where(['createtime >=' => $yesterday_timestamp, 'createtime <' => $today_timestamp])
            ->count();
        $data['last7days']['n'] = Order::query($condition)
            ->where(['createtime >=' => $today_timestamp, 'createtime <' => $last7days_timestamp])
            ->count();
        $data['month']['n'] = Order::query($condition)
            ->where(['createtime >=' => $ft_mon_timestamp, 'createtime <' => $tomorrow_timestamp])
            ->count();
        $data['lastmonth']['n'] = Order::query($condition)
            ->where(['createtime >=' => $fl_mon_timestamp, 'createtime <' => $ft_mon_timestamp])
            ->count();

        return ['device_stat' => $device_stat, 'data' => $data];
    }

    public static function homepageOrderStat(): array
    {
        $user = self::getUser();
        $agent = $user->getAgent();

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
            'agent_id' => $agent->getId(),
            'createtime >=' => $s_date->getTimestamp(),
            'createtime <' => $e_date->getTimestamp(),
        ];

        $res = Device::query(['agent_id' => $agent->getId()])->findAll();
        $devices = [];
        $device_keys = [];
        foreach ($res as $item) {
            $devices[$item->getId()] = $item->getName() . ' - ' . $item->getImei();
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

        $res = Order::query($condition)->findAll();

        /** @var orderModelObj $item */
        foreach ($res as $item) {
            $amount = $item->getCommissionPrice();

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

        ksort($data);

        return [
            'data' => $data,
            'total' => $total,
            'devices' => $devices
        ];
    }

    public static function aliAuthCode(): array
    {
        $auth_code = request::str('authcode');

        $aop = new AopClient();
        $aop->appId = settings('alixapp.id');
        $aop->rsaPrivateKey = settings('alixapp.prikey');
        $aop->alipayrsaPublicKey = settings('alixapp.pubkey');

        $request = new AlipaySystemOauthTokenRequest();
        $request->setGrantType('authorization_code');
        $request->setCode($auth_code);

        try {
            $result = $aop->execute($request);
            if ($result->error_response) {
                return err('获取用户信息失败：' . $result->error_response->sub_msg);
            }

            $user_id = $result->alipay_system_oauth_token_response->user_id;
            $user = User::get($user_id, true);

            $user_info = [];
            if ($user) {
                $user_info['user_id'] = $user_id;
                if (!(empty($user->getNickname()) && empty($user->getAvatar()))) {
                    $user_info['user_info'] = [
                        'nickname' => $user->getNickname(),
                        'avatar' => $user->getAvatar()
                    ];
                }
            } else {
                if (User::create(['openid' => $user_id, 'app' => User::ALI])) {
                    $user_info['user_id'] = $user_id;
                } else {
                    return err('保存用户失败!');
                }
            }

            return $user_info;

        } catch (Exception $e) {
            return err('获取用户信息失败：' . $e->getMessage());
        }
    }

    public static function aliUserInfo(): array
    {
        $user = self::getUser();

        $nickname = request('nickname');
        $avatar = request('avatar');

        $user->setNickname($nickname);
        $user->setAvatar($avatar);

        if ($user->save()) {
            return ['msg' => '保存成功！', 'status' => true];
        }

        return error(State::ERROR, '保存失败!');
    }

    public static function userOrders(): array
    {
        //用户订单
        $user = self::getUser();

        $query = Order::query();
        $condition = [];

        $condition['openid'] = $user->getOpenid();

        $order_no = request::trim('order');
        if ($order_no) {
            $condition['order_id LIKE'] = '%' . $order_no . '%';
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

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $query->where($condition);
        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $accounts = [];
        $orders = [];
        /** @var orderModelObj $entry */
        foreach ($query->page($page, $page_size)->orderBy('id DESC')->findAll() as $entry) {
            //公众号信息
            if (empty($accounts[$entry->getAccount()])) {
                $account = Account::findOneFromName($entry->getAccount());
                if ($account) {
                    $accounts[$entry->getAccount()] = [
                        'name' => $account->getName(),
                        'clr' => $account->getClr(),
                        'title' => $account->getTitle(),
                        'img' => $account->getImg(),
                        'qrcode' => $account->getQrcode(),
                    ];
                }
            }

            $data = Order::format($entry, true);
            if ($accounts[$data['account']]) {
                $data['clr'] = $accounts[$data['account']]['clr'];
            } else {
                if ($data['refund']) {
                    $data['clr'] = '#ccc';
                } else {
                    $data['clr'] = $data['from']['color'];
                }
            }

            //出货结果
            $data['result'] = $entry->getExtraData('pull.result', []);

            if ($entry->getPrice() > 0) {
                $data['type'] = '支付订单';
                if ($data['refund']) {
                    $data['status'] = '已退款';
                } else {
                    if (is_error($data['result'])) {
                        $data['status'] = '故障';
                    } else {
                        $data['status'] = '成功';
                    }
                }
            } elseif ($entry->getBalance()) {
                $data['type'] = '余额订单';
            } else {
                $data['type'] = '免费订单';
            }

            $orders[] = $data;
        }

        return [
            'orders' => $orders,
            'accounts' => $accounts,
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total
        ];
    }

    /**
     * 获取当前已登录的用户.
     *
     * @return userModelObj
     */
    public static function getUser(): userModelObj
    {
        static $user = null;

        if ($user) {
            return $user;
        }

        if (request::has('token')) {
            $login_data = LoginData::get(request('token'));
            if (empty($login_data)) {
                JSON::fail('请先登录后再请求数据！[101]');
            }
            $user = User::get($login_data->getUserId());
        } elseif (request::has('user_id')) {
            $user_id = request('user_id');
            $user = User::get($user_id, true, User::ALI);
        } else {
            JSON::fail('请先登录后再请求数据！[102]');
        }

        if (empty($user)) {
            JSON::fail('请先登录后再请求数据！[103]');
        }

        if ($user->isBanned()) {
            if (isset($login_data)) {
                $login_data->destroy();
            }
            JSON::fail('暂时无法使用，请联系管理员！');
        }

        return $user;
    }

    public static function doUserLogin($res): array
    {
        Util::logToFile("user", $res);
        $openid = strval($res['openId']);
        $user = User::get($openid, true, User::WxAPP);
        if (empty($user)) {
            $user = User::create([
                'app' => User::WxAPP,
                'openid' => $openid,
                'nickname' => $res['nickName'],
                'avatar' => $res['avatarUrl'],
                'mobile' => $res['phoneNumber'] ?? '',
                'createtime' => time(),
            ]);

            if (empty($user)) {
                return error(State::ERROR, '创建用户失败！');
            }
        } else {
            $user->setNickname($res['nickName']);
            $user->setAvatar($res['avatarUrl']);
            if (isset($res['phoneNumber'])) {
                $user->setMobile($res['phoneNumber']);
            }
        }

        $user->set('fansData', $res);
        $user->save();

        if ($user->isBanned()) {
            return error(State::ERROR, '登录失败，请稍后再试！');
        }

        //清除原来的登录信息
        foreach (LoginData::user(['user_id' => $user->getId()])->findAll() as $entry) {
            $entry->destroy();
        }

        $token = sha1($openid . Util::random(16));
        $data = [
            'src' => LoginData::User,
            'user_id' => $user->getId(),
            'session_key' => '',
            'openid_x' => $openid,
            'token' => $token,
        ];

        if (LoginData::create($data)) {
            return ['token' => $token];
        }

        return error(State::ERROR, '登录失败，请稍后再试！');
    }

    /**
     * 获取用户的取货码列表
     * @return array
     */
    public static function voucherList(): array
    {
        $user = self::getUser();

        $params = [
            'owner_id' => $user->getId(),
        ];

        $type = request('type');
        if (isset($type)) {
            $params['type'] = $type;
        }

        $params['page'] = max(1, request::int('page'));
        $params['pagesize'] = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $res = GoodsVoucher::logList($params);
        if (is_error($res)) {
            JSON::fail($res);
        }

        return $res;
    }

    /**
     * 获取设备相关的商品列表
     * @return array
     */
    public static function getGoodsList(): array
    {
        $imei = request::str('device');

        $device = Device::get($imei, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        return ['goods' => $device->getGoodsList(null, ['allowPay'])];
    }
}
