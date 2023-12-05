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
use zovye\App;
use zovye\BlueToothProtocol;
use zovye\domain\Account;
use zovye\domain\Advertising;
use zovye\domain\Agent;
use zovye\domain\Balance;
use zovye\domain\Device;
use zovye\domain\DeviceFeedback;
use zovye\domain\Goods;
use zovye\domain\GoodsVoucher;
use zovye\domain\LoginData;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\Job;
use zovye\JSON;
use zovye\Log;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goods_voucher_logsModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\DeviceUtil;
use zovye\util\Helper;
use zovye\util\LocationUtil;
use zovye\util\Util;
use zovye\We7;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

class bluetooth
{
    public static function getDeviceInfo(): array
    {
        $device_id = Request::str('device');

        if (empty($device_id)) {
            $device_id = Request::str('imei');
        }

        /** @var deviceModelObj $device */
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $data = [
            'id' => $device->getId(),
            'name' => $device->getName(),
            'mobile' => '',
        ];

        $agent = $device->getAgent();
        if ($agent) {
            $data['mobile'] = $agent->getMobile();
        }

        if ($device->isBlueToothDevice()) {
            $data['protocol'] = $device->getBlueToothProtocolName();
        }

        return ['data' => $data];
    }

    /**
     * 获取设备相关的广告
     */
    public static function ads(): array
    {
        $device_id = Request::str('device');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        //广告列表
        $ads = $device->getAds(Advertising::WELCOME_PAGE);
        $result = [];
        foreach ($ads as $adv) {
            if ($adv['extra']['images']) {
                foreach ($adv['extra']['images'] as $image) {
                    if ($image) {
                        $result[] = [
                            'id' => intval($adv['id']),
                            'name' => strval($adv['name']),
                            'image' => Util::toMedia($image),
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
     */
    public static function onConnected(): array
    {
        $device_id = Request::str('device');

        /** @var deviceModelObj $device */
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return err('不是蓝牙设备！');
        }

        $proto = $device->getBlueToothProtocol();
        if (empty($proto)) {
            return err('无法加载蓝牙协议！');
        }

        $device->setBluetoothStatus(Device::BLUETOOTH_CONNECTED);
        $device->setMcbOnline(true);
        $device->setLastOnline(TIMESTAMP);
        $device->save();

        $data = Request::str('data');
        $cmd = $proto->onConnected($device->getBUID(), $data);
        if ($cmd) {
            Device::createBluetoothCmdLog($device, $cmd);

            return [
                'data' => $cmd->getEncoded(BlueToothProtocol::BASE64),
                'hex' => $cmd->getEncoded(BlueToothProtocol::HEX),
            ];
        }

        return ['msg' => 'Ok'];
    }

    public static function deviceStatus(): array
    {
        $device_id = Request::str('device');

        /** @var deviceModelObj $device */
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return err('不是蓝牙设备！');
        }

        $proto = $device->getBlueToothProtocol();
        if (empty($proto)) {
            return err('无法加载蓝牙协议！');
        }

        if ($proto->support(BlueToothProtocol::QOE) && $device->isLowBattery()) {
            return err('设备电量低，暂时无法购买！');
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
            'data' => $cmd->getEncoded(BlueToothProtocol::BASE64),
            'hex' => $cmd->getEncoded(BlueToothProtocol::HEX),
        ];
    }

    /**
     * 收到蓝牙设备的数据
     */
    public static function onDeviceData(): array
    {
        $device_id = Request::str('device');

        /** @var deviceModelObj $device */
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return err('不是蓝牙设备！');
        }

        $proto = $device->getBlueToothProtocol();
        if (empty($proto)) {
            return err('无法加载蓝牙协议！');
        }

        $data = Request::str('data');
        $response = $proto->parseResponse($device->getBUID(), $data);
        if (empty($response)) {
            return err('无法解析消息！');
        }

        Device::createBluetoothEventLog($device, $response);

        if ($response->isOpenResult()) {
            $order = Order::getLastOrderOfDevice($device);
            if ($order) {
                if (empty($order->getExtraData('bluetooth.raw'))) {
                    $order->setExtraData('bluetooth.raw', $response->getEncodeData());
                    if ($response->isOpenResultOk()) {
                        $order->setResultCode(0);
                        $order->setBluetoothResultOk();
                    } elseif ($response->isOpenResultFail()) {
                        $order->setResultCode($response->getErrorCode());
                        $order->setBluetoothResultFail($response->getMessage());
                        if (Helper::isAutoRefundEnabled($device)) {
                            //启动退款
                            Job::refund($order->getOrderNO(), $response->getMessage());
                        }
                    }
                    if (!$order->save()) {
                        Log::error('order', [
                            'order' => $order->profile(),
                            'error' => 'save order failed',
                        ]);
                    }
                }
            }

            if ($response->isOpenResultFail()) {
                $code = intval($response->getErrorCode());
                $message = strval($response->getMessage());
                $device->setLastError($code, $message);
                $device->scheduleErrorNotifyJob();
            } else {
                $device->cleanLastError();
            }
        }

        if ($response->isReady()) {
            $device->setBluetoothStatus(Device::BLUETOOTH_READY);
        }

        $data = [];

        if (!$proto->support(BlueToothProtocol::QOE)) {
            $device->setQoe(-1);
        } else {
            if ($response->hasBatteryValue()) {
                $battery = $response->getBatteryValue();

                $device->setQoe($battery);
                if ($device->isLowBattery()) {
                    $device->setLastError(Device::ERROR_LOW_BATTERY, Device::desc(Device::ERROR_LOW_BATTERY));
                    Job::deviceEventNotify($device, Device::EVENT_LOW_BATTERY);
                }

                $data['battery'] = $battery;
            }
        }

        $device->save();

        $cmd = $response->getAttachedCMD();
        if ($cmd) {
            Device::createBluetoothCmdLog($device, $cmd);
            $data['data'] = $cmd->getEncoded(BlueToothProtocol::BASE64);
            $data['hex'] = $cmd->getEncoded(BlueToothProtocol::HEX);
        }

        return $data;
    }

    /**
     * 取货码 出货
     */
    public static function voucherGet(userModelObj $user): array
    {
        $device_id = Request::str('device');
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $goods_id = Request::int('goodsId');
        $code = Request::str('code');

        /** @var goods_voucher_logsModelObj $v */
        $v = GoodsVoucher::getLogByCode($code);
        if (empty($v)) {
            return err('取货码不存在！');
        }

        if (!$v->isValid()) {
            return err('无效的取货码!');
        }

        if ($v->getGoodsId() != $goods_id) {
            return err('无法领取这个商品！');
        }

        if ($device->isBlueToothDevice()) {
            try {
                $result = DeviceUtil::open(
                    ['level' => LOG_GOODS_VOUCHER, $device, $user, $v, 'goodsId' => $goods_id, 'online' => false]
                );
            } catch (Exception $e) {
                return err($e->getMessage());
            }

            if (is_error($result)) {
                return $result;
            }

            $order = Order::get($result['orderId']);
            if (empty($order)) {
                return err('出货失败：找不到订单！');
            }

            //设置蓝牙出货标专为0，表示出货结果未确认!
            $order->setExtraData('bluetooth', [
                'result' => 0,
                'deviceBUID' => $device->getBUID(),
            ]);

            if (!$order->save()) {
                return err('出货失败：无法保存订单数据！');
            }

            return [
                'msg' => $result['msg'],
                'data' => $result['result'],
            ];
        }

        return err('出货失败：不是蓝牙主板！');
    }

    /**
     * 创建支付订单
     */
    public static function orderCreate(userModelObj $user): array
    {
        if ($user->isBanned()) {
            return err('用户暂时无法使用！');
        }

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $device_id = Request::str('device');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return err('不是蓝牙设备！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        if (!$device->lockAcquire(3)) {
            return err('设备正忙，请稍后再试！');
        }

        $goods_id = Request::int('goodsId');
        if (empty($goods_id)) {
            return err('没有指定商品！');
        }

        return Helper::createWxAppOrder($user, $device, $goods_id);
    }

    public static function orderGet(): array
    {
        $device_id = Request::str('device');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $order_no = Request::str('orderNO');

        $order = Order::getLastOrderOfDevice($device);
        if (empty($order)) {
            return err('没有订单！');
        }

        if ($order->getOrderNO() != $order_no) {
            return err('订单号不匹配！');
        }

        if ($order->isBluetoothResultOk()) {
            return err('订单已成功！');
        }

        if ($order->isBluetoothResultFail()) {
            return err('订单已失败！');
        }

        $data = $order->getExtraData('pull.result', '');
        if (empty($data)) {
            return err('出货加密凭证为空，请联系管理员！');
        }

        return [
            'data' => $data,
            'hex' => bin2hex(base64_decode($data)),
        ];
    }

    /**
     * 查询订单状态
     */
    public static function orderStats(): array
    {
        $device_id = Request::str('device');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isBlueToothDevice()) {
            return err('不是蓝牙设备！');
        }

        $order = Order::getLastOrderOfDevice($device);
        if (empty($order)) {
            return err('没有找到订单！');
        }

        if ($order->getBluetoothDeviceBUID() !== $device->getBUID()) {
            return err('订单与设备不匹配！');
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

    public static function feedback(userModelObj $user): array
    {
        $device_id = Request::str('device');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        $text = Request::trim('text');
        $pics = Request::str('pics');

        $data = [
            'device_id' => $device->getId(),
            'user_id' => $user->getId(),
            'text' => $text,
            'pics' => serialize($pics),
            'createtime' => time(),

        ];

        if (DeviceFeedback::create($data)) {
            return ['msg' => '反馈成功！'];
        }

        return err('反馈失败！');
    }

    public static function deviceAds(): array
    {
        $device_id = Request::str('device');
        if (empty($device_id)) {
            $device_id = Request::str('deviceid');
        }

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $type = Request::int('typeid');
        $num = Request::int('num', 10);

        return DeviceUtil::getAds($device, $type, $num);
    }

    public static function orderDefault(agentModelObj $agent): array
    {
        $condition = [];
        $condition['agent_id'] = $agent->getId();

        $devices = [];
        $device_keys = [];

        $res = Device::query(We7::uniacid(['agent_id' => $agent->getId()]))->findAll();

        /** @var deviceModelObj $item */
        foreach ($res as $item) {
            $devices[$item->getId()] = $item->getName().' - '.$item->getImei();
            $device_keys[] = $item->getId();
        }

        if (Request::has('deviceid')) {
            $d_id = Request::int('deviceid');
            if (in_array($d_id, $device_keys, true)) {
                $condition['device_id'] = $d_id;
            } else {
                $condition['device_id'] = -1;
            }
        }

        $order_no = Request::trim('order');
        if ($order_no) {
            $condition['order_id LIKE'] = '%'.$order_no.'%';
        }

        $way = Request::trim('way');
        if ($way == 'free') {
            if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
                $condition['src'] = [Order::ACCOUNT, Order::FREE, Order::BALANCE];
            } else {
                $condition['src'] = [Order::ACCOUNT, Order::FREE];
            }
        } elseif ($way == 'fee') {
            if (App::isBalanceEnabled() && Balance::isPayOrder()) {
                $condition['src'] = [Order::PAY, Order::BALANCE];
            } else {
                $condition['src'] = Order::PAY;
            }
        } elseif ($way == 'refund') {
            $condition['refund'] = 1;
        }

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = Order::query($condition);
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
                    $info = LocationUtil::getIpInfo($data['ip']);
                    if ($info) {
                        $entry->set('ip_info', $info);
                    }
                }
                if ($info) {
                    $json = is_string($info) ? json_decode($info, true) : $info;
                    if ($json) {
                        $data['ip_info'] = "{$json['data']['province']}{$json['data']['city']}{$json['data']['district']}";
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
            'total' => $total,
        ];
    }

    public static function homepageDefault(agentModelObj $agent): array
    {
        $condition = [];
        $condition['agent_id'] = $agent->getId();

        $device_stat = [];

        $time_less_15 = new DateTime('-15 min');
        $power_time = $time_less_15->getTimestamp();
        $device_stat['all'] = Device::query($condition)->count();
        $device_stat['on'] = Device::query('last_ping IS NOT NULL AND last_ping > '.$power_time)->count();
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

    public static function homepageOrderStat(agentModelObj $agent): array
    {
        $date_limit = Request::array('datelimit');
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

        $condition = [
            'agent_id' => $agent->getId(),
            'createtime >=' => $s_date->getTimestamp(),
            'createtime <' => $e_date->getTimestamp(),
        ];

        $res = Device::query(['agent_id' => $agent->getId()])->findAll();
        $devices = [];
        $device_keys = [];
        /** @var deviceModelObj $item */
        foreach ($res as $item) {
            $devices[$item->getId()] = $item->getName().' - '.$item->getImei();
            $device_keys[] = $item->getId();
        }

        if (Request::has('deviceid')) {
            $d_id = Request::int('deviceid');
            if (in_array($d_id, $device_keys, true)) {
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
            'devices' => $devices,
        ];
    }

    public static function aliAuthCode(): array
    {
        $aop = new AopClient();
        $aop->appId = settings('alixapp.id');
        $aop->rsaPrivateKey = settings('alixapp.prikey');
        $aop->alipayrsaPublicKey = settings('alixapp.pubkey');

        $request = new AlipaySystemOauthTokenRequest();
        $request->setGrantType('authorization_code');
        $request->setCode(Request::str('authcode'));

        try {
            $response = $aop->execute($request);
            if ($response->error_response) {
                return err('获取用户信息失败：'.$response->error_response->sub_msg);
            }

            $result = [];

            $openid = $response->alipay_system_oauth_token_response->user_id;
            $user = User::get($openid, true);

            if ($user) {
                $result['user_info'] = [
                    'nickname' => $user->getNickname(),
                    'avatar' => $user->getAvatar(),
                ];
            } else {
                $user = User::create(['openid' => $openid, 'app' => User::ALI]);
                if (!$user) {
                    return err('保存用户失败!');
                }
            }

            $token = Util::getTokenValue();

            $data = [
                'src' => LoginData::ALI_APP_USER,
                'user_id' => $user->getId(),
                'session_key' => '',
                'openid_x' => $user->getOpenid(),
                'token' => $token,
            ];

            if (LoginData::create($data)) {
                $result['user_id'] = $token;
            }

            return $result;

        } catch (Exception $e) {
            return err('获取用户信息失败：'.$e->getMessage());
        }
    }

    public static function aliUserInfo(userModelObj $ali_user): array
    {
        $nickname = Request::str('nickname');
        $avatar = Request::str('avatar');

        $ali_user->setNickname($nickname);
        $ali_user->setAvatar($avatar);

        if ($ali_user->save()) {
            return ['msg' => '保存成功！', 'status' => true];
        }

        return err('保存失败!');
    }

    public static function userOrders(userModelObj $user): array
    {
        $query = Order::query();
        $condition = [];

        $condition['openid'] = $user->getOpenid();

        $order_no = Request::trim('order');
        if ($order_no) {
            $condition['order_id LIKE'] = '%'.$order_no.'%';
        }

        $way = Request::trim('way');
        if ($way == 'free') {
            if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
                $condition['src'] = [Order::ACCOUNT, Order::FREE, Order::BALANCE];
            } else {
                $condition['src'] = [Order::ACCOUNT, Order::FREE];
            }
        } elseif ($way == 'fee') {
            if (App::isBalanceEnabled() && Balance::isPayOrder()) {
                $condition['src'] = [Order::PAY, Order::BALANCE];
            } else {
                $condition['src'] = Order::PAY;
            }
        } elseif ($way == 'refund') {
            $condition['refund'] = 1;
        }

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

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
            'total' => $total,
        ];
    }

    /**
     * 获取用户的取货码列表
     */
    public static function voucherList(userModelObj $user): array
    {
        $params = [
            'owner_id' => $user->getId(),
        ];

        $type = Request::str('type');
        if ($type) {
            $params['type'] = $type;
        }

        $params['page'] = max(1, Request::int('page'));
        $params['pagesize'] = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $res = GoodsVoucher::logList($params);
        if (is_error($res)) {
            JSON::fail($res);
        }

        return $res;
    }

    /**
     * 获取设备相关的商品列表
     */
    public static function getGoodsList(): array
    {
        $device_id = Request::str('device');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        return ['goods' => $device->getGoodsList(null, [Goods::AllowPay]), true];
    }
}
