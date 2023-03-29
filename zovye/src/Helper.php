<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;
use zovye\model\device_logsModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

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
     * @return bool
     */
    public static function needsTplAccountsData(deviceModelObj $device = null): bool
    {
        $theme = self::getTheme($device);

        return !in_array($theme, ['balance', 'balance2', 'spa', 'spec']);
    }

    /**
     * 设备故障时，订单是否需要自动退款
     * @param null $obj
     * @return bool
     */
    public static function NeedAutoRefund($obj = null): bool
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
     * @param deviceModelObj $device
     * @return bool
     */
    public static function MustFollowAccount(deviceModelObj $device): bool
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

    public static function isZeroBonus(deviceModelObj $device, $w): bool
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

    public static function preparePullData(orderModelObj $order, deviceModelObj $device, userModelObj $user): array
    {
        $pull_data = [
            'online' => false,
            'timeout' => App::getDeviceWaitTimeout(),
            'userid' => $user->getOpenid(),
            'num' => $order->getNum(),
            'user-agent' => $order->getExtraData('from.user_agent'),
            'ip' => $order->getExtraData('from.ip'),
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

        return $pull_data;
    }

    /**
     * @param orderModelObj $order
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param int $level
     * @param $data
     * @return array
     */
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

        $pull_data = self::preparePullData($order, $device, $user);

        if ($goods['lottery']) {
            $mcb_channel = intval($goods['lottery']['size']);
            if ($goods['lottery']['index']) {
                $pull_data['index'] = intval($goods['lottery']['index']);
                $pull_data['unit'] = 1;//1 表示以inch为单位
            }
        } else {
            $mcb_channel = Device::cargoLane2Channel($device, $goods['cargo_lane']);
        }

        if ($mcb_channel == Device::CHANNEL_INVALID) {
            return err('商品货道配置不正确！');
        }

        $pull_data['channel'] = $mcb_channel;

        $result = $device->pull($pull_data);

        //v1版本新版本返回数据包含在json的data下
        if (!is_error($result)) {
            $locker = $device->payloadLockAcquire(3);
            if (empty($locker)) {
                return err('设备正忙，请重试！');
            }
            $res = $device->resetPayload([$goods['cargo_lane'] => -1], "订单：{$order->getOrderNO()}");
            if (is_error($res)) {
                return err('保存库存失败！');
            }
            $locker->unlock();
        }

        $device->save();

        $log_data = [
            'order' => $order->getId(),
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

        if (Util::mustValidateLocation($user, $device)) {
            return err('设备位置不在允许的范围内！');
        }

        $goods = $device->getGoods($goods_id);
        if (empty($goods) || empty($goods[Goods::AllowBalance])) {
            return err('无法兑换这个商品，请联系管理员！');
        }

        if (Balance::isFreeOrder()) {
            $free_limits = Util::getFreeOrderLimits($user, $device);
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

        App::setContainer($user);

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
        Job::orderPayResult($order_no);

        //加入一个支付超时任务
        $res = Job::orderTimeout($order_no);
        if (empty($res) || is_error($res)) {
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

        App::setContainer($user);

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
        if (empty($res) || is_error($res)) {
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
        string $serial = ''
    ) {
        if ($total_price < 100) {
            return err('支付金额不能小于1元！');
        }

        App::setContainer($user);

        list($order_no, $data) = Pay::createXAppPay(
            $device,
            $user,
            [
                'title' => '充电订单',
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
        if (empty($res) || is_error($res)) {
            return err('创建支付任务失败！');
        }

        //加入一个支付超时任务
        $res = Job::orderTimeout($order_no);

        if (empty($res) || is_error($res)) {
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

        App::setContainer($user);

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
                'ip' => Util::getClientIp(),
            ]
        );

        if (is_error($data)) {
            return err('创建支付失败: '.$data['message']);
        }

        //加入一个支付结果检查
        $res = Job::fuelingPayResult($order_no);
        if (empty($res) || is_error($res)) {
            return err('创建支付任务失败！');
        }

        //加入一个支付超时任务
        $res = Job::orderTimeout($order_no);

        if (empty($res) || is_error($res)) {
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

        App::setContainer($user);

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

            $res = Util::getDistance($location, ['lng' => $lng, 'lat' => $lat]);
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
        Log::debug('payment', [
            'device' => $device->profile(),
            'data' => $params,
        ]);

        try {
            $code = $params['code'] ?? '';
            if (empty($code)) {
                throw new RuntimeException('无效的付款码，请重新扫码！');
            }

            $order_no = "P$code";
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

            if (empty($goods) || empty($goods[Goods::AllowPay]) || $goods['price'] < 1 || $goods['num'] < 1) {
                throw new RuntimeException('对不起，暂时无法购买这个商品！');
            }

            list($order_no, $data) = Pay::createQrcodePay($device, $code, $goods, [
                'order_no' => $order_no,
                'level' => LOG_GOODS_PAY,
                'total' => 1,
                'qrcode' => $params,
            ]);

            if (is_error($data)) {
                throw new RuntimeException('创建支付失败: '.$data['message']);
            }

            //加入一个支付结果检查
            Job::orderPayResult($order_no);

            //加入一个支付超时任务
            $res = Job::orderTimeout($order_no);
            if (empty($res) || is_error($res)) {
                throw new RuntimeException('创建支付任务失败！');
            }

        } catch (RuntimeException $e) {
            $device->appShowMessage($e->getMessage(), 'error');
        }
    }
}
