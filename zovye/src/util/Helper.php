<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\util;

use DateTimeImmutable;
use Exception;
use RuntimeException;
use zovye\App;
use zovye\Config;
use zovye\domain\Account;
use zovye\domain\Balance;
use zovye\domain\BalanceLog;
use zovye\domain\CommissionBalance;
use zovye\domain\Device;
use zovye\domain\DeviceLogs;
use zovye\domain\Goods;
use zovye\domain\GoodsExpireAlert;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\domain\Questionnaire;
use zovye\domain\User;
use zovye\Job;
use zovye\JSON;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\device_logsModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goods_expire_alertModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;
use zovye\Pay;
use zovye\Request;
use zovye\Session;
use zovye\We7;
use zovye\Wx;
use function zovye\err;
use function zovye\getArray;
use function zovye\is_error;
use function zovye\isEmptyArray;
use function zovye\request;
use function zovye\settings;

class Helper
{
    public static function getTheme(deviceModelObj $device = null)
    {
        if ($device) {
            $theme = $device->settings('extra.theme', '');
            if ($theme) {
                return $theme;
            }
            $agent = $device->getAgent();
            if ($agent) {
                $theme = $agent->settings('agentData.device.theme', '');
                if ($theme) {
                    return $theme;
                }
            }
        }

        return settings('device.get.theme', 'default');
    }

    /**
     * 如果当前皮肤需要tpl_data中获取任务列表，否返回true
     * @param deviceModelObj|null $device
     */
    public static function isThemeNeedsPrefetchAccountsData(deviceModelObj $device = null): bool
    {
        static $disabled_pre_fetch_themes = ['balance', 'balance2', 'spa', 'spec', 'summer', 'puai'];

        return !in_array(self::getTheme($device), $disabled_pre_fetch_themes, true);
    }

    /**
     * 设备故障时，订单是否需要自动退款
     * @param null $obj
     */
    public static function isAutoRefundEnabled($obj = null): bool
    {
        if ($obj instanceof deviceModelObj) {
            $device = $obj;
        } elseif ($obj instanceof orderModelObj) {
            $device = $obj->getDevice();
        }

        if (isset($device)) {
            $agent = $device->getAgent();
            if ($agent) {
                $agent_auto_refund = intval($agent->settings('agentData.misc.auto_ref'));
                if ($agent_auto_refund == 1) {
                    return true;
                } elseif ($agent_auto_refund == 2) {
                    return false;
                }
            }
        }

        return settings('order.rollback.enabled', false);
    }

    /**
     * 是否设置必须关注公众号以后才能购买商品
     */
    public static function isMustFollowAccountEnabled(deviceModelObj $device): bool
    {
        if (!App::isMustFollowAccountEnabled()) {
            return false;
        }

        $enabled = $device->settings('extra.mfa.enable');
        if (isset($enabled) && $enabled != -1) {
            return boolval($enabled);
        }

        $agent = $device->getAgent();
        if ($agent) {
            $enabled = $agent->settings('agentData.mfa.enable');
            if (isset($enabled) && $enabled != -1) {
                return boolval($enabled);
            }
        }

        $enabled = settings('mfa.enable');

        return boolval($enabled);
    }

    public static function getOrderPullLog(orderModelObj $order): array
    {
        $condition = [
            'createtime >=' => $order->getCreatetime(),
            'createtime <' => $order->getCreatetime() + 3600,
            'data REGEXP' => "s:5:\"order\";i:{$order->getId()};",
        ];

        $device = $order->getDevice();
        if ($device) {
            $condition['title'] = $device->getImei();
        }

        $query = DeviceLogs::query($condition);

        $list = [];
        /** @var device_logsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'imei' => $entry->getTitle(),
                'title' => Device::formatPullTitle($entry->getLevel()),
                'price' => $entry->getData('price'),
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

        return $list;
    }

    public static function isZeroBonusEnabled(deviceModelObj $device, $w): bool
    {
        if (App::isZeroBonusEnabled()) {
            $zero = settings('custom.bonus.zero', []);

            $enabled = false;
            if (empty($zero['order'])) {
                $enabled = true;
            } else {
                if ($w == Order::PAY_STR && $zero['order']['p']) {
                    $enabled = true;
                } elseif ($w == Order::FREE_STR && $zero['order']['f']) {
                    $enabled = true;
                } elseif ($w == Order::BALANCE_STR) {
                    if (Balance::isFreeOrder() && $zero['order']['f']) {
                        $enabled = true;
                    } elseif (Balance::isPayOrder() && $zero['order']['p']) {
                        $enabled = true;
                    }
                }
            }

            if ($enabled) {
                $v = $device->settings('extra.custom.bonus.zero.v', -1.0);
                if ($v < 0) {
                    $agent = $device->getAgent();
                    if ($agent) {
                        $v = $agent->settings('agentData.custom.bonus.zero.v', -1.0);
                    }
                    if ($v < 0) {
                        $v = $zero['v'];
                    }
                }

                return $v > 0 && mt_rand(1, 10000) <= intval($v * 100);
            }
        }

        return false;
    }

    public static function preparePullData(
        ?orderModelObj $order,
        deviceModelObj $device,
        ?userModelObj $user,
        array $goods
    ): array {
        $pull_data = [
            'online' => false,
            'timeout' => App::getDeviceWaitTimeout(),
            'userid' => $user ? $user->getOpenid() : '',
            'num' => $order ? $order->getNum() : 1,
            'user-agent' => $order ? $order->getExtraData('from.user_agent') : '',
            'ip' => $order ? $order->getExtraData('from.ip') : CLIENT_IP,
        ];

        $loc = $device->settings('extra.location', []);
        if ($loc && $loc['lng'] && $loc['lat']) {
            $pull_data['location'] = [
                'device' => [
                    'lng' => $loc['lng'],
                    'lat' => $loc['lat'],
                ],
            ];
        }

        if ($goods['lottery']) {
            $mcb_channel = intval($goods['lottery']['size']);
            if ($mcb_channel < 1) {
                return err('商品长度不正确！');
            }
            if (isset($goods['lottery']['index'])) {
                $pull_data['index'] = intval($goods['lottery']['index']);
            }
            $pull_data['unit'] = isset($goods['lottery']['unit']) ? intval(
                $goods['lottery']['unit']
            ) : 1; //1 默认1，inch为单位
        } elseif ($goods['ts']) {
            $mcb_channel = intval($goods['ts']['duration']);
            if ($mcb_channel < 1) {
                return err('商品长度不正确！');
            }
        } else {
            $mcb_channel = Device::cargoLane2Channel($device, $goods['cargo_lane'] ?? -1);
            if ($mcb_channel == Device::CHANNEL_INVALID) {
                return err('商品货道配置不正确！');
            }
        }

        $pull_data['channel'] = $mcb_channel;

        return $pull_data;
    }

    public static function pullGoods(
        orderModelObj $order,
        deviceModelObj $device,
        userModelObj $user,
        int $level,
        $data
    ): array {
        //todo 处理优惠券
        //$voucher = $pay_log->getVoucher();

        $goods = $device->getGoods($data['goods_id']);
        if (empty($goods)) {
            return err('找不到对应的商品！');
        }

        if ($goods['num'] < 1) {
            return err('对不起，商品库存不足！');
        }

        $pull_data = self::preparePullData($order, $device, $user, $goods);
        if (is_error($pull_data)) {
            return $pull_data;
        }

        //保存货道
        $order->setExtraData('device.ch', $pull_data['channel']);

        //请求出货
        $result = $device->pull($pull_data);

        //处理库存
        $res = DeviceUtil::resetDevicePayload($device, $result, $goods, "订单：{$order->getOrderNO()}");
        if (is_error($res)) {
            return $res;
        }

        $device->save();

        $log_data = [
            'order' => $order->getId(),
            'ch' => $pull_data['channel'],
            'result' => $result,
            'user' => $user->profile(),
            'goods' => $goods,
            'price' => $data['price'],
            'balance' => $data['balance'] ?? 0,
            'voucher' => isset($voucher) ? ['id' => $voucher->getId()] : [],
        ];

        $device->goodsLog($level, $log_data);

        if (!is_error($result)) {
            $device->updateAppRemain();
        }

        return $result;
    }

    public static function exchange(userModelObj $user, $device_uid, $goods_id, $num, $order_no = '')
    {
        if (!App::isBalanceEnabled()) {
            return err('这个功能没有启用！');
        }

        $device = Device::get($device_uid, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (LocationUtil::mustValidate($user, $device)) {
            return err('设备位置不在允许的范围内！');
        }

        $goods = $device->getGoods($goods_id);
        if (empty($goods) || empty($goods[Goods::AllowBalance])) {
            return err('无法兑换这个商品，请联系管理员！');
        }

        if (Balance::isFreeOrder()) {
            $free_limits = self::getFreeOrderLimits($user, $device);
            if ($free_limits < $num) {
                return err('今天的免费兑换额度已用完，请明天再来吧！');
            }
        }

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $num = min(App::getOrderMaxGoodsNum(), max($num, 1));
        if ($num < 1) {
            return err('对不起，商品数量不正确！');
        }

        if ($goods['num'] < $num) {
            return err('对不起，商品数量不足！');
        }

        $balance = $user->getBalance();
        if ($goods['balance'] * $num > $balance->total()) {
            return err('您的积分不够！');
        }

        if (empty($order_no)) {
            $order_no = Order::makeUID($user, $device, sha1(REQUEST_ID));
        }

        $ip = $user->getLastActiveIp();

        if (Job::createBalanceOrder($order_no, $user, $device, $goods_id, $num, $ip)) {
            return $order_no;
        }

        return err('失败，请稍后再试！');
    }

    public static function createWxAppOrder(
        userModelObj $user,
        deviceModelObj $device,
        $goodsOrPackageId,
        $num = 1,
        $is_package = false,
        $order_no = ''
    ) {
        if ($is_package) {
            $package = $device->getPackage($goodsOrPackageId);
            if (empty($package)) {
                return err('找不到这个商品套餐！');
            }

            if (empty($package['isOk'])) {
                return err('暂时无法购买这个商品套餐！');
            }

            $num = 1;
            $total_price = $package['price'];

            $goods = $package;

        } else {
            $goods = $device->getGoods($goodsOrPackageId);
            if (empty($goods) || empty($goods[Goods::AllowPay]) || $goods['price'] < 1) {
                return err('无法购买这个商品，请联系管理员！');
            }

            if ($goods['num'] < $num) {
                return err('对不起，商品数量不足！');
            }

            //获取用户折扣
            $discount = User::getUserDiscount($user, $goods, $num);
            $total_price = $goods['price'] * $num - $discount;
        }

        if ($total_price < 1) {
            return err('支付金额不能为零！');
        }

        Session::setContainer($user);

        list($order_no, $data) = Pay::createXAppPay($device, $user, $goods, [
            'level' => LOG_GOODS_PAY,
            'discount' => $discount ?? 0,
            'order_no' => $order_no,
            'total' => $num,
            'price' => $total_price,
        ]);

        if (is_error($data)) {
            return err('创建支付失败: '.$data['message']);
        }

        //加入一个支付结果检查
        $res = Job::orderPayResult($order_no);
        if (!$res) {
            JSON::fail('创建支付任务失败！');
        }

        //加入一个支付超时任务
        $res = Job::orderTimeout($order_no);
        if (!$res) {
            return err('创建支付任务失败！');
        }

        $data['orderNO'] = $order_no;

        return $data;
    }

    public static function createForDeviceRenewal(userModelObj $user, deviceModelObj $device, int $years)
    {
        $total_price = $device->getYearRenewalPrice() * $years;
        if ($total_price < 1) {
            return err('支付金额不能为零！');
        }

        Session::setContainer($user);

        list($order_no, $data) = Pay::createXAppPay(
            $device,
            $user,
            [
                'title' => '设备年费',
                'price' => $total_price,
            ],
            [
                'level' => LOG_DEVICE_RENEWAL_PAY,
                'price' => $total_price,
                'years' => $years,
            ]
        );

        if (is_error($data)) {
            return err('创建支付失败: '.$data['message']);
        }

        //加入一个支付结果检查
        $res = Job::deviceRenewalPayResult($order_no);
        if (!$res) {
            return err('创建支付任务失败！');
        }

        $data['orderNO'] = $order_no;

        return $data;
    }

    public static function createChargingOrder(
        userModelObj $user,
        deviceModelObj $device,
        int $chargerID,
        int $total_price,
        string $serial = '',
        string $remark = ''
    ) {
        if ($total_price < 100) {
            return err('支付金额不能小于1元！');
        }

        Session::setContainer($user);

        list($order_no, $data) = Pay::createXAppPay(
            $device,
            $user,
            [
                'title' => !empty($remark) ? $remark : '充电订单',
                'price' => $total_price,

            ],
            [
                'level' => LOG_CHARGING_PAY,
                'price' => $total_price,
                'order_no' => $serial,
                'chargerID' => $chargerID,
            ]
        );

        if (is_error($data)) {
            return err('创建支付失败: '.$data['message']);
        }

        //加入一个支付结果检查
        $res = Job::chargingPayResult($order_no);
        if (!$res) {
            return err('创建支付任务失败！');
        }

        //加入一个支付超时任务
        $res = Job::orderTimeout($order_no);

        if (!$res) {
            return err('创建支付任务失败！');
        }

        $data['orderNO'] = $order_no;

        return $data;
    }

    public static function createFuelingOrder(
        userModelObj $user,
        deviceModelObj $device,
        int $chargerID,
        int $total_price,
        string $serial = ''
    ) {

        if ($total_price < 1) {
            return err('支付金额不正确！');
        }

        Session::setContainer($user);

        list($order_no, $data) = Pay::createXAppPay(
            $device,
            $user,
            [
                'title' => '加注订单',
                'price' => $total_price,
            ],
            [
                'level' => LOG_FUELING_PAY,
                'price' => $total_price,
                'order_no' => $serial,
                'chargerID' => $chargerID,
                'ip' => CLIENT_IP,
            ]
        );

        if (is_error($data)) {
            return err('创建支付失败: '.$data['message']);
        }

        //加入一个支付结果检查
        $res = Job::fuelingPayResult($order_no);
        if (!$res) {
            return err('创建支付任务失败！');
        }

        //加入一个支付超时任务
        $res = Job::orderTimeout($order_no);
        if (!$res) {
            return err('创建支付任务失败！');
        }

        $data['orderNO'] = $order_no;

        return $data;
    }

    public static function createRechargeOrder(userModelObj $user, int $price, string $title = '')
    {
        if ($price < 1) {
            return err('支付金额不能为零！');
        }

        Session::setContainer($user);

        list($order_no, $data) = Pay::createXAppPay(
            Device::getDummyDevice(),
            $user,
            [
                'title' => empty($title) ? '充值订单' : $title,
                'price' => $price,
            ],
            [
                'level' => LOG_RECHARGE,
                'price' => $price,
            ]
        );

        if (is_error($data)) {
            return err('创建支付失败: '.$data['message']);
        }

        //加入一个支付结果检查
        Job::rechargePayResult($order_no);

        $data['orderNO'] = $order_no;

        return $data;
    }

    public static function validateLocation(userModelObj $user, deviceModelObj $device, $lat, $lng)
    {
        $data = [
            'validated' => false,
            'time' => time(),
            'lng' => $lng,
            'lat' => $lat,
        ];

        $user->setLastActiveData('location', $data);

        //用户扫描设备后的定位信息
        $location = $device->settings('extra.location.tencent', $device->settings('extra.location'));
        if ($location && $location['lng'] && $location['lat']) {

            $distance = App::getUserLocationValidateDistance(1);
            $agent = $device->getAgent();
            if ($agent) {
                if ($agent->settings('agentData.location.validate.enabled')) {
                    $distance = $agent->settings('agentData.location.validate.distance', $distance);
                }
            }

            $res = LocationUtil::getDistance($location, ['lng' => $lng, 'lat' => $lat]);
            if (is_error($res)) {
                Log::error('location', $res);

                return err('哎呀，出错了');
            }

            if ($res > $distance) {
                $user->setLastActiveDevice();

                return err('哎呀，设备太远了');
            }
        }

        $user->setLastActiveData('location.validated', true);

        return true;
    }

    public static function createQrcodeOrder(deviceModelObj $device, $params = [])
    {
        Log::debug('qr_pay', [
            'device' => $device->profile(),
            'data' => $params,
        ]);

        try {
            $code = $params['code'] ?? '';
            if (empty($code)) {
                throw new RuntimeException('无效的付款码，请重新扫码！');
            }

            //根据付款码设置环境
            if (Pay::isWxPayQRCode($code)) {
                $_SESSION['wx_user_id'] = $code;
            } else {
                $_SESSION['ali_user_id'] = $code;
            }

            if (!Locker::try($code)) {
                throw new RuntimeException('锁定失败，请重试！');
            }

            $user = User::getPseudoUser($code, '<匿名用户>');
            if (empty($user)) {
                throw new RuntimeException('系统错误，创建用户失败！');
            }

            $order_no = substr("U{$user->getId()}P".sha1($code), 0, MAX_ORDER_NO_LEN);
            if (Order::exists($order_no)) {
                throw new RuntimeException('订单已存在！');
            }

            $goods_id = intval($params['goods'] ?? 0);
            if ($goods_id > 0) {
                $goods = $device->getGoods($goods_id);
            } else {
                $lane_id = intval($params['lane'] ?? 0);
                $goods = $device->getGoodsByLane($lane_id);
            }

            if (empty($goods)) {
                throw new RuntimeException('对不起，没有可用商品！');
            }

            if (App::isAllCodeEnabled() && $goods[Goods::AllowFree]) {
                if (!Job::createAccountOrder([
                    'account' => Account::getPseudoAccount()->getId(),
                    'device' => $device->getId(),
                    'user' => $user->getId(),
                    'goods' => $goods['id'],
                    'orderUID' => $order_no,
                ])) {
                    throw new RuntimeException('系统错误，创建任务失败！');
                }

                return;
            }

            if (empty($goods[Goods::AllowPay]) || $goods['price'] < 1) {
                throw new RuntimeException('对不起，暂时无法购买这个商品！');
            }

            if ($goods['num'] < 1) {
                throw new RuntimeException('对不起，商品库存不足！');
            }

            list($order_no, $data) = Pay::createQRCodePay($device, $code, $goods, [
                'order_no' => $order_no,
                'level' => LOG_GOODS_PAY,
                'total' => 1,
                'qrcode' => $params,
            ]);

            if (is_error($data) && $data['errno'] != 100) {
                throw new RuntimeException('对不起，创建支付失败: '.$data['message']);
            }

            $user_id = $data['openid'] ?? ($data['payer_uid'] ?? $data['user_id']);
            if ($user_id) {
                $user = User::get($user_id, true);
                if ($user) {
                    $pay_log = Pay::getPayLog($order_no);
                    if ($pay_log) {
                        $pay_log->setData('user', $user->getOpenid());
                        $pay_log->save();
                    }
                }
            }

            //加入一个支付结果检查
            $res = Job::orderPayResult($order_no);
            if (!$res) {
                throw new RuntimeException('系统错误，创建支付任务失败！');
            }

            //加入一个支付超时任务
            $res = Job::orderTimeout($order_no);
            if (!$res) {
                throw new RuntimeException('系统错误，创建支付任务失败！');
            }

        } catch (RuntimeException $e) {
            Log::error('qr_pay', [
                'error' => $e->getMessage(),
            ]);
            $device->appShowMessage($e->getMessage(), 'error');
        }
    }

    public static function getUserCommissionLogs(userModelObj $user): array
    {
        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        $query = $user->getCommissionBalance()->log();
        $query->page($page, $page_size);

        $query->orderBy('createtime DESC');

        $result = [];
        foreach ($query->findAll() as $log) {
            $result[] = CommissionBalance::format($log);
        }

        return $result;
    }

    public static function sendSysTemplateMessageTo($w, $data = [])
    {
        $tpl_id = Config::WxPushMessage('config.sys.tpl_id');
        if (empty($tpl_id)) {
            return err('没有配置模板消息id！');
        }

        $user_id = Config::WxPushMessage("config.sys.$w.user.id", 0);
        if (empty($user_id)) {
            return err('没有指定代理审核管理员！');
        }

        $user = User::get($user_id);
        if (empty($user)) {
            return err('找不到指定代理审核管理员！');
        }

        return Wx::sendTemplateMsg([
            'touser' => $user->getOpenid(),
            'template_id' => $tpl_id,
            'data' => $data,
        ]);
    }

    public static function sendWxPushMessageTo(deviceModelObj $device, string $event, array $params)
    {
        $agent = $device->getAgent();

        if ($agent && !$agent->isBanned()) {
            $list = self::getNotificationOpenIds($agent, $event);

            foreach ($device->getKeepers() as $keeper) {
                if ($keeper->settings("notice.$event")) {
                    $user = $keeper->getUser();
                    if ($user && !$user->isBanned()) {
                        $list[] = $user->getOpenid();
                    }
                }
            }

            // 去掉重复
            $list = array_unique($list);

            foreach ($list as $openid) {
                $params['touser'] = $openid;
                $result = Wx::sendTemplateMsg($params);
                if (is_error($result)) {
                    Log::error('sendEventTemplateMsg', [
                        'agent' => $agent->profile(),
                        'data' => $params,
                        'result' => $result,
                    ]);
                }
            }

            $device->setLastNotification($event);
        }
    }

    public static function getWxPushMessageConfig($data = []): array
    {
        $config = Config::WxPushMessage('config', []);

        $result = [];

        $result['device']['online'] = [
            'enabled' => $data['device']['online'],
            'tpl_id' => getArray($config, 'device.online.tpl_id', ''),
        ];

        $result['device']['offline'] = [
            'enabled' => $data['device']['offline'],
            'tpl_id' => getArray($config, 'device.offline.tpl_id', ''),
        ];

        $result['device']['error'] = [
            'enabled' => $data['device']['error'],
            'tpl_id' => getArray($config, 'device.error.tpl_id', ''),
        ];

        $result['device']['low_battery'] = [
            'enabled' => $data['device']['low_battery'],
            'tpl_id' => getArray($config, 'device.low_battery.tpl_id', ''),
        ];

        $result['device']['low_remain'] = [
            'enabled' => $data['device']['low_remain'],
            'tpl_id' => getArray($config, 'device.low_remain.tpl_id', ''),
        ];

        $result['order']['succeed'] = [
            'enabled' => $data['order']['succeed'],
            'tpl_id' => getArray($config, 'order.succeed.tpl_id', ''),
        ];

        $result['order']['failed'] = [
            'enabled' => $data['order']['failed'],
            'tpl_id' => getArray($config, 'order.failed.tpl_id', ''),
        ];

        return $result;
    }

    /**
     * 返回省份列表
     */
    public static function getProvinceList(): array
    {
        return [
            'p1' => '北京',
            'p2' => '天津',
            'p3' => '上海',
            'p4' => '重庆',
            'p5' => '河北',
            'p6' => '山西',
            'p7' => '辽宁',
            'p8' => '吉林',
            'p9' => '黑龙江',
            'p10' => '浙江',
            'p11' => '江苏',
            'p12' => '安徽',
            'p13' => '福建',
            'p14' => '江西',
            'p15' => '山东',
            'p16' => '河南',
            'p17' => '湖北',
            'p18' => '湖南',
            'p19' => '广东',
            'p20' => '海南',
            'p21' => '四川',
            'p22' => '贵州',
            'p23' => '云南',
            'p24' => '陕西',
            'p25' => '甘肃',
            'p26' => '青海',
            'p27' => '内蒙古',
            'p28' => '广西',
            'p29' => '西藏',
            'p30' => '宁夏',
            'p31' => '新疆',
        ];
    }

    public static function getSettingsNavs(): array
    {
        $navs = [
            'device' => '设备',
            'user' => '用户',
            'agent' => '代理商',
            'wxapp' => '小程序',
            'commission' => '佣金',
            'balance' => '积分',
            'account' => '任务',
            'notice' => '通知',
            'payment' => '支付',
            'misc' => '其它',
            'upgrade' => '系统升级',
        ];

        if (!App::isBalanceEnabled()) {
            unset($navs['balance']);
        }

        return $navs;
    }

    public static function getAndCheckWithdraw($id)
    {
        /** @var commission_balanceModelObj $balance_obj */
        $balance_obj = CommissionBalance::findOne(['id' => $id, 'src' => CommissionBalance::WITHDRAW]);
        if (empty($balance_obj)) {
            return err('操作失败，请刷新页面后再试！');
        }

        $openid = $balance_obj->getOpenid();
        $user = User::get($openid, true);
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        if (!$user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
            return err('用户无法锁定，请重试！');
        }

        if ($balance_obj->getUpdatetime()) {
            $state = $balance_obj->getExtraData('state');
            if ($state === 'mchpay') {
                $MCHPayResult = $balance_obj->getExtraData('mchpayResult');
                if (empty($MCHPayResult['payment_no']) && $MCHPayResult['detail_status'] === 'FAIL') {
                    return $balance_obj;
                }
            }

            return err('操作失败，请刷新页面后再试！');
        }

        return $balance_obj;
    }

    public static function getWe7Material($typename, $page, $page_size = DEFAULT_PAGE_SIZE): array
    {
        $title = '';
        $list = [];

        if ($typename == 'text') {
            $title = '填写推送消息的文本';
        } elseif ($typename == 'image') {
            $title = '选择推送消息的图片';
            $page = max(1, intval($page));
            $page_size = max(1, $page_size);
            We7::load()->model('material');
            $list = We7::material_list('image', MATERIAL_WEXIN, ['page_index' => $page, 'page_size' => $page_size]);
        } elseif ($typename == 'mpnews') {
            $title = '选择推送消息的图文';
            We7::load()->model('material');
            $list = We7::material_news_list(MATERIAL_WEXIN)['material_list'];
        }

        return ['title' => $title, 'list' => $list];
    }

    public static function getAgentFNs($enable = true): array
    {
        $val = $enable ? 1 : 0;

        return [
            'F_tj' => $val, //统计管理
            'F_xj' => $val, //下级管理
            'F_sb' => $val, //设备管理
            'F_zc' => $val, //设备注册
            'F_qz' => $val, //缺货设备
            'F_gz' => $val, //故障设备
            'F_yy' => $val, //运营人员
            'F_gg' => $val, //广告管理
            'F_xf' => $val, //吸粉管理
            'F_pt' => $val, //平台管理
            'F_wt' => $val, //常见问题
            'F_wd' => $val, //文档中心
            'F_xh' => $val, //型号管理
            'F_sp' => $val, //商品管理
        ];
    }

    /**
     * 获取需要通知的openid list
     */
    public static function getNotificationOpenIds(agentModelObj $agent, string $event): array
    {
        $result = [];

        if ($event) {
            if ($agent->getAgentData("notice.$event") && !$agent->isBanned()) {
                $result[$agent->getId()] = $agent->getOpenid();
            }

            foreach ((array)$agent->getAgentData('partners') as $user_id => $data) {
                $user = User::get($user_id);
                if ($user && !$user->isBanned()) {
                    $enabled = $user->settings("partnerData.notice.$event");
                    if (!isset($enabled) || $enabled) {
                        $result[$user->getId()] = $user->getOpenid();
                    }
                }
            }
        }

        return array_values($result);
    }

    /**
     * 获取控制服务器回调网址
     */
    public static function getCtrlServCallbackUrl(array $params = []): string
    {
        $params = array_merge(
            [
                'm' => APP_NAME,
                'sign' => settings('ctrl.signature'),
            ],
            $params
        );

        return Util::murl('ctrl', $params);
    }

    public static function createApiRedirectFile(string $filename, string $do, array $params = [], callable $fn = null)
    {
        We7::make_dirs(dirname(ZOVYE_ROOT.$filename));

        $headers = is_array($params['headers']) ? $params['headers'] : [];
        unset($params['headers']);

        if (empty($headers['HTTP_USER_AGENT'])) {
            $headers['HTTP_USER_AGENT'] = 'api_redirect';
        }
        if (empty($headers['HTTP_X_REQUESTED_WITH'])) {
            $headers['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        }

        $header_str = '';
        foreach ($headers as $name => $val) {
            $header_str .= "\$_SERVER['$name'] = '$val';\r\n";
        }

        $memo = !empty($params['memo']) ? strval($params['memo']) : 'API转发程序';
        unset($params['memo']);

        if ($do) {
            $params['do'] = $do;
        }

        $appName = APP_NAME;
        $uniacid = We7::uniacid();
        $appPath = realpath(ZOVYE_ROOT.'../../app');

        $content = "<?php
/**
 * $memo
 *
 * @author jin@stariture.com
 * @url www.stariture.com
 */

$header_str
\$_GET['m'] = '$appName';
\$_GET['i'] = $uniacid;
\$_GET['c'] = 'entry';
";
        foreach ($params as $name => $val) {
            $content .= "\$_GET['$name'] = '$val';\r\n";
        }

        if ($fn) {
            $content .= $fn();
        }

        $content .= "
chdir('$appPath');
include './index.php';
";

        return file_put_contents(ZOVYE_ROOT.$filename, $content);
    }

    /**
     * 订单统计
     *
     * @param orderModelObj $order 订单对象
     *
     */
    public static function orderStatistics(orderModelObj $order)
    {
        $name = $order->getAccount();
        if ($name) {
            $account = Account::findOneFromName($name);
            if ($account) {
                $order_limits = $account->getOrderLimits();
                if ($order_limits > 0) {
                    //更新公众号统计，并检查吸粉总量
                    $total = Order::query(['account' => $account->getName()])->limit($order_limits + 1)->count();
                    if ($total >= $order_limits) {
                        $account->setState(Account::BANNED);
                        Account::updateAccountData();
                    }
                }
            }
        }
    }

    /**
     * 检查订单数量是否达到指定数量，true 表示已达到，false表示没有
     */
    public static function checkLimit(
        accountModelObj $account,
        userModelObj $user = null,
        array $params = [],
        int $limit = 0
    ): bool {
        $result = CacheUtil::cachedCall(0, function () use ($account, $user, $params, $limit) {
            $arr = [];
            if ($account->isTask()) {
                $cond = array_merge($params, [
                    'account_id' => $account->getId(),
                ]);
                if ($user) {
                    $cond['user_id'] = $user->getId();
                }
                $arr[] = [
                    BalanceLog::query($cond),
                    'count',
                ];
            } elseif ($account->isQuestionnaire()) {
                $cond = array_merge($params, [
                    'level' => $account->getId(),
                ]);
                if ($user) {
                    $cond['title'] = $user->getOpenid();
                }
                $arr[] = [
                    Questionnaire::log($cond),
                    'count',
                ];
            } else {
                $cond = array_merge($params, [
                    'account_id' => $account->getId(),
                ]);
                if ($user) {
                    $cond['user_id'] = $user->getId();
                }
                $arr[] = [
                    BalanceLog::query($cond),
                    'count',
                ];
                $cond2 = array_merge($params, [
                    'account' => $account->getName(),
                ]);
                if ($user) {
                    $cond2['openid'] = $user->getOpenid();
                }
                $arr[] = [
                    Order::query($cond2),
                    'sum',
                ];
            }

            foreach ($arr as $e) {
                list($query, $m) = $e;

                $query->limit($limit);
                $total = $m == 'sum' ? $query->sum('num') : $query->count();

                if ($total >= $limit) {
                    return true;
                }

                $limit -= $total;
            }

            // 条件不满足时，抛出异常，抑制缓存
            throw new RuntimeException();
        }, 'checkLimit', $account->getId(), $user ? $user->getId() : 0, $params, $limit);

        return !is_error($result);
    }

    /**
     * 检查用户是否符合公众号设置的限制条件
     */
    public static function checkAccountLimits(userModelObj $user, accountModelObj $account, array $params = [])
    {
        //检查性别，手机限制
        $limits = $account->get('limits');
        if (is_array($limits)) {
            $limit_fn = [
                'male' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 1) {
                        return err('不允许男性用户');
                    }

                    return true;
                },
                'female' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 2) {
                        return err('不允许女性用户');
                    }

                    return true;
                },
                'unknown_sex' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 0) {
                        return err('不允许未知性别用户');
                    }

                    return true;
                },
                'ios' => function ($val) {
                    if ($val == 0 && Session::getUserPhoneOS() == 'ios') {
                        return err('不允许ios手机');
                    }

                    return true;
                },
                'android' => function ($val) {
                    if ($val == 0) {
                        $os = Session::getUserPhoneOS();
                        if ($os == 'android' || $os == 'unknown') {
                            return err('不允许android手机');
                        }
                    }

                    return true;
                },
            ];

            foreach ($limits as $item => $val) {
                if ($val == 0 && $limit_fn[$item]) {
                    $fn = $limit_fn[$item];
                    $res = $fn($val);
                    if (is_error($res)) {
                        return $res;
                    }
                }
            }

            if (!isEmptyArray($limits['area'])) {
                $info = LocationUtil::getIpInfo(CLIENT_IP);
                if ($info) {
                    if ($limits['area']['province'] && $info['data']['province'] != $limits['area']['province']) {
                        return err('区域（省）不允许！');
                    }

                    if ($limits['area']['city'] && $info['data']['city'] != $limits['area']['city']) {
                        return err('区域（市）不允许！');
                    }
                }
            }
        }

        if ($params['unfollow'] || in_array('unfollow', $params, true)) {
            if (Helper::checkLimit($account, $user, [], 1)) {
                return err('您已经完成了该任务！');
            }
        }

        $sc_name = $account->getScname();

        if ($sc_name == Account::DAY) {
            $time = new DateTimeImmutable('00:00');
        } elseif ($sc_name == Account::WEEK) {
            $time = date('D') == 'Mon' ? new DateTimeImmutable('00:00') : new DateTimeImmutable('last Mon 00:00');
        } elseif ($sc_name == Account::MONTH) {
            $time = new DateTimeImmutable('first day of this month 00:00');
        } else {
            return err('任务设置不正确！');
        }

        //count，单个用户在每个周期内可领取数量
        $count = $account->getCount();
        if ($count > 0) {
            $desc = [
                Account::DAY => '今天已经领过了，明天再来吧！',
                Account::WEEK => '下个星期再来试试吧！',
                Account::MONTH => '这个月的免费额度已经用完啦！',
            ];

            if (Helper::checkLimit(
                $account,
                $user,
                ['createtime >=' => $time->getTimestamp(),],
                $count
            )) {
                return err($desc[$sc_name]);
            }
        }

        //scCount, 所有用户在每个周期内总数量
        $sc_count = $account->getSccount();
        if ($sc_count > 0) {
            if (Helper::checkLimit($account, null, [
                'createtime >=' => $time->getTimestamp(),
            ], $sc_count)) {
                return err('任务免费额度已用完！');
            }
        }

        //total，单个用户累计可领取数量
        $total = $account->getTotal();
        if ($total > 0) {
            if (Helper::checkLimit($account, $user, [], $total)) {
                return err('您已经完成这个任务了！');
            }
        }

        //$orderLimits，最大订单数量
        $order_limits = $account->getOrderLimits();
        if ($order_limits > 0) {
            if (Helper::checkLimit($account, null, [], $order_limits)) {
                return err('公众号免费额度已用完！！');
            }
        }

        return true;
    }

    public static function checkBalanceAvailable(userModelObj $user, accountModelObj $account)
    {
        if ($account->getBonusType() != Account::BALANCE) {
            return err('公众号没有配置积分奖励！');
        }

        return Helper::checkAccountLimits($user, $account);
    }

    /**
     * 检查用户是否被限制，则返回true，否则返回false
     */
    public static function checkFlashEggDeviceLimit(deviceModelObj $device, userModelObj $user): bool
    {
        $limit = $device->settings('extra.limit', []);
        if (isEmptyArray($limit)) {
            return false;
        }

        if (in_array($limit['scname'], [Account::DAY, Account::WEEK, Account::MONTH], true)) {
            if ($limit['scname'] == Account::DAY) {
                $time = new DateTimeImmutable('00:00');
            } elseif ($limit['scname'] == Account::WEEK) {
                $time = date('D') == 'Mon' ? new DateTimeImmutable('00:00') : new DateTimeImmutable('last Mon 00:00');
            } elseif ($limit['scname'] == Account::MONTH) {
                $time = new DateTimeImmutable('first day of this month 00:00');
            } else {
                $time = null;
            }

            if ($time) {
                //单个用户周期内限制
                if ($limit['count'] > 0) {
                    $query = Order::query([
                        'src' => [Order::FREE, Order::ACCOUNT],
                        'device_id' => $device->getId(),
                        'openid' => $user->getOpenid(),
                        'createtime >=' => $time->getTimestamp(),
                    ]);
                    $query->limit($limit['count']);
                    if ($query->sum('num') >= $limit['count']) {
                        return true;
                    }
                }
                //所有用户周期内限制
                if ($limit['sccount'] > 0) {
                    $query = Order::query([
                        'src' => [Order::FREE, Order::ACCOUNT],
                        'device_id' => $device->getId(),
                        'createtime >=' => $time->getTimestamp(),
                    ]);
                    $query->limit($limit['sccount']);
                    if ($query->sum('num') >= $limit['sccount']) {
                        return true;
                    }
                }
            }
        }

        //单个用户累计限制
        if ($limit['total'] > 0) {
            $query = Order::query([
                'src' => [Order::FREE, Order::ACCOUNT],
                'device_id' => $device->getId(),
                'openid' => $user->getOpenid(),
            ]);
            $query->limit($limit['total']);
            if ($query->sum('num') >= $limit['total']) {
                return true;
            }
        }

        //所有用户累计限制
        if ($limit['all'] > 0) {
            $query = Order::query([
                'src' => [Order::FREE, Order::ACCOUNT],
                'device_id' => $device->getId(),
            ]);
            $query->limit($limit['all']);
            if ($query->sum('num') >= $limit['all']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断用户在指定公众号以及指定设备是否还有免费额度
     *
     * @param userModelObj $user 用户
     * @param accountModelObj $account 公众号
     * @param deviceModelObj $device 设备
     * @param array $params 更多条件
     *
     */
    public static function checkAvailable(
        userModelObj $user,
        accountModelObj $account,
        deviceModelObj $device,
        array $params = []
    ) {
        $res = self::checkFreeOrderLimits($user, $device);
        if (is_error($res)) {
            return $res;
        }

        if (empty($params['ignore_assigned'])) {
            $assign_data = $account->settings('assigned', []);
            if (!DeviceUtil::isAssigned($device, $assign_data)) {
                return err('没有允许从这个设备访问该公众号！');
            }
        }

        if (App::isFlashEggEnabled()) {
            if (Helper::checkFlashEggDeviceLimit($device, $user)) {
                return err('领取数量趣过设备限制！');
            }
            $totalPerDevice = $account->getTotalPerDevice();
            if ($totalPerDevice > 0 && Helper::checkLimit(
                    $account,
                    $user,
                    ['device_id' => $device->getId()],
                    $totalPerDevice
                )) {
                return err('领取数量已经达到单台设备最大领取限制！');
            }
        }

        return Helper::checkAccountLimits($user, $account, $params);
    }

    public static function checkFreeOrderLimits(userModelObj $user, deviceModelObj $device)
    {
        if ($user->isFreeCD()) {
            return err('暂时不能免费领取，请稍后再试！');
        }

        //每日免费额度限制
        if (Helper::getUserTodayFreeNum($user, $device) < 1) {
            return err('今天已经不能再领了，明天再来吧！');
        }

        //全部免费额度限制
        if (Helper::getUserFreeNum($user, $device) < 1) {
            return err('您的免费额度已用完！');
        }

        return true;
    }

    public static function getFreeOrderLimits(userModelObj $user, deviceModelObj $device)
    {
        //每日免费额度限制
        $today = Helper::getUserTodayFreeNum($user, $device);
        if ($today < 1) {
            return 0;
        }

        //全部免费额度限制
        $all = Helper::getUserFreeNum($user, $device);
        if ($all < 1) {
            return 0;
        }

        return min($today, $all);
    }

    public static function getAttachmentFileName(string $dirname, string $filename): string
    {
        $full_path = ATTACHMENT_ROOT.$dirname.$filename;

        if (!is_dir(ATTACHMENT_ROOT.$dirname)) {
            We7::make_dirs(ATTACHMENT_ROOT.$dirname);
        }

        return $full_path;
    }

    public static function parseIdsFromGPC(): array
    {
        $ids = [];

        $raw = request('ids');
        if ($raw) {
            if (is_string($raw)) {
                $ids = explode(',', $raw);
            } elseif (is_array($raw)) {
                $ids = $raw;
            } else {
                $ids = [intval($raw)];
            }
            foreach ($ids as $index => $id) {
                $id = intval($id);
                if ($id > 0) {
                    $ids[$index] = $id;
                }
            }
        }

        return $ids;
    }

    public static function parseAgentFNsFromGPC(): array
    {
        $FNs = Helper::getAgentFNs(false);
        foreach ($FNs as $index => &$enable) {
            $enable = Request::bool($index) ? 0 : 1;
        }

        return $FNs;
    }

    /**
     * 返回用户还需要关注的公众号列表
     */
    public static function getRequireAccounts(
        deviceModelObj $device,
        userModelObj $user,
        accountModelObj $account,
        array $excepts = []
    ): array {
        $accounts = [];

        //获取多个关注公众号设置
        $qr_codes = $account->get('qrcodesData', []);
        if ($qr_codes && is_array($qr_codes)) {
            $accounts = $qr_codes;
        }

        //如果没有开启关注多个公众号，但开启了公众号推广，则加入推广公众号
        if (empty($accounts) && settings('misc.accountsPromote')) {
            $res = Account::match($device, $user, ['unfollow']);
            if ($res && !isset($res[$account->getUid()])) {
                $acc = current($res);

                $accounts[$acc['uid']] = [
                    'img' => $acc['qrcode'],
                    'xid' => $acc['uid'],
                    'url' => $acc['url'],
                    'descr' => $acc['descr'],
                ];
            }
        }

        //去掉主号
        unset($accounts[$account->getUid()]);

        //去掉已经关注过的号
        $visited_accounts = $user->getLastActiveData('accounts', []);
        $visited_accounts = is_array($visited_accounts) ? $visited_accounts : [];

        //排除
        if ($excepts) {
            $excepts = is_array($excepts) ? $excepts : [$excepts];
            foreach ($excepts as $uid) {
                if ($uid) {
                    $visited_accounts[$uid] = time();
                }
            }

            $user->setLastActiveData('accounts', $visited_accounts);
        }

        $accounts = array_diff_key($accounts, $visited_accounts);

        return array_values($accounts);
    }

    /**
     * 获取用户今日免费可领取的数量
     */
    public static function getUserTodayFreeNum(userModelObj $user, deviceModelObj $device): int
    {
        $limits = 0;

        $agent = $device->getAgent();
        if ($agent) {
            $limits = $agent->getAgentData('misc.maxFree', 0);
        }

        $limits = !empty($limits) ? $limits : settings('user.maxFree', 0);

        return !empty($limits) ? max(0, $limits - $user->getTodayFreeTotal()) : 1;
    }

    public static function getUserFreeNum(userModelObj $user, deviceModelObj $device): int
    {
        $limits = 0;

        $agent = $device->getAgent();
        if ($agent) {
            $limits = $agent->getAgentData('misc.maxTotalFree', 0);
        }

        $limits = !empty($limits) ? $limits : settings('user.maxTotalFree', 0);

        return !empty($limits) ? max(0, $limits - $user->getFreeTotal()) : 1;
    }

    public static function getPayloadWithAlertData(deviceModelObj $device, bool $detail = true): array
    {
        $payload = $device->getPayload($detail);
        if ($payload['cargo_lanes']) {
            foreach ($payload['cargo_lanes'] as $index => &$lane) {
                $lane['alert'] = [];
                $alert = GoodsExpireAlert::getFor($device, $index);
                if ($alert) {
                    $expire_at = $alert->getExpiredAt();
                    $lane['alert'] = [
                        'status' => GoodsExpireAlert::getStatus($alert),
                        'expired_at' => $expire_at > 0 ? date('Y-m-d', $expire_at) : '',
                        'pre_days' => $alert->getPreDays(),
                        'invalid_if_expired' => $alert->getInvalidIfExpired(),
                    ];
                }
            }
        }

        return $payload;
    }

    public static function removeInvalidAlert(deviceModelObj $device)
    {
        if (App::isGoodsExpireAlertEnabled()) {
            $all = GoodsExpireAlert::query(['device_id' => $device->getId()])->findAll();
            $payload = $device->getPayload();
            /** @var goods_expire_alertModelObj $alert */
            foreach ($all as $alert) {
                if (empty($payload['cargo_lanes'][$alert->getLaneId()])) {
                    $alert->destroy();
                }
            }
        } else {
            GoodsExpireAlert::remove(['device_id' => $device->getId()]);
        }
    }

    public static function upload($name, $type = 'image')
    {
        if (empty($_FILES[$name])) {
            return err('上传失败[01]');
        }

        We7::load()->func('file');

        $res = We7::file_upload($_FILES[$name], $type);

        if (is_error($res)) {
            return err('上传失败[02]');
        }

        if ($res['success'] && $res['path']) {
            try {
                We7::file_remote_upload($res['path']);
            } catch (Exception $e) {
                Log::error('upload', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $res['path'];
    }
}
