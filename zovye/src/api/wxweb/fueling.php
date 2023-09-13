<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\Config;
use zovye\Device;
use zovye\Helper;
use zovye\Order;
use zovye\Request;
use zovye\User;
use function zovye\err;

class fueling
{
    public static function rechargeInfo() {
        return Config::fueling('vip.recharge.promotion', []);
    }

    /**
     * 设备详情
     */
    public static function deviceDetail(): array
    {
        $user = common::getWXAppUser();

        $device_id = Request::str('deviceId');
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $data = $device->profile(true);
        $data['vip'] = \zovye\Fueling::isVIP($user, $device);

        $fuelingNOWData = $user->fuelingNOWData();
        if ($fuelingNOWData) {
            $data['fueling'] = [
                'serial' => $fuelingNOWData['serial'],
            ];
        }

        $chargerID = Request::int('chargerID');
        $deviceFuelingNOWData = $device->fuelingNOWData($chargerID);
        if ($deviceFuelingNOWData && $deviceFuelingNOWData['user'] != $user->getId()) {
            $data['fueling'] = [
                'error' =>  '设备正在使用中!',
            ];
        }

        $goods = $device->getGoodsByLane($chargerID, [], false);
        if ($goods) {
            $data['goods'] = $goods;
        } else {
            $data['goods'] = [];
        }

        return $data;
    }

    /**
     * 开始加注
     */
    public static function start()
    {
        $user = common::getWXAppUser();

        if ($user->isBanned()) {
            return err('对不起，用户暂时无法使用！');
        }

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = Request::int('chargerID');

        $card = \zovye\Fueling::isVIP($user, $device) ? $user->getVIPCard() : $user->getCommissionBalanceCard();

        return \zovye\Fueling::start('', $card, $device, $chargerID);
    }

    /**
     * 停止加注
     */
    public static function stop()
    {
        $user = common::getWXAppUser();

        return \zovye\Fueling::stop($user);
    }

    /**
     * 加注状态
     */
    public static function status(): array
    {
        $serial = Request::str('serial');

        return \zovye\Fueling::orderStatus($serial);
    }

    public static function payForFueling(): array
    {
        $user = common::getWXAppUser();

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isFuelingDevice()) {
            return err('设备类型不正确！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        $chargerID = Request::int('chargerID');

        $charging_data = $device->fuelingNOWData($chargerID);
        if (!empty($charging_data)) {
            return err('设备正在使用中！');
        }

        $price = intval(round(Request::float('price', 0, 2) * 100));

        return Helper::createFuelingOrder($user, $device, $chargerID, $price, Order::makeSerial($user));
    }

    /**
     * 订单列表
     */
    public static function orderList(): array
    {
        $user = common::getWXAppUser();

        $query = Order::query([
            'openid' => $user->getOpenid(),
            'result_code' => 0,
            'src' => [Order::FUELING, Order::FUELING_UNPAID],
        ]);

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $keywords = Request::trim('keywords');
        if ($keywords) {
            $query->where(['order_id REGEXP' => $keywords]);
        }

        $query->orderby('id desc');

        $list = [];
        foreach ($query->findAll() as $order) {
            $list[] = Order::format($order, true);
        }

        return $list;
    }

    /**
     * 订单详情
     */
    public static function orderDetail(): array
    {
        $serial = Request::str('serial');
        $user = common::getWXAppUser();

        $order = Order::get($serial, true);
        if (empty($order)) {
            return err('找不到这个订单！');
        }

        $orderOwner = $order->getUser();
        if ($orderOwner && $orderOwner->getId() != $user->getId()) {
            return err('无法查看该订单！');
        }

        if ($order->isFuelingResultFailed()) {
            $data = $order->getFuelingResult();

            return err('订单没有完成，故障：'.$data['re']);
        }

        if (!$order->isFuelingFinished()) {
            return err('订单没有完成！');
        }

        return Order::format($order, true);
    }
}