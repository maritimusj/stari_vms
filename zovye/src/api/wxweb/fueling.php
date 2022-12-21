<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\Device;
use zovye\Helper;
use zovye\Order;
use zovye\request;
use zovye\User;
use zovye\VIP;
use function zovye\err;

class fueling
{
    /**
     * 设备详情
     */
    public static function deviceDetail(): array
    {
        $user = common::getWXAppUser();

        $device_id = request::str('deviceId');
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $profile = $device->profile(true);
        $profile['vip'] = false;

        $agent = $device->getAgent();
        $vip = VIP::getFor($agent, $user);

        if ($vip && $vip->hasPrivilege($device)) {
            $profile['vip'] = true;
        }

        return $profile;
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

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = request::int('chargerID');

        return \zovye\Fueling::start('', $user->getCommissionBalanceCard(), $device, $chargerID);
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
        $serial = request::str('serial');

        return \zovye\Fueling::orderStatus($serial);
    }

    public static function payForFueling(): array
    {
        $user = common::getWXAppUser();

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isFuelingDevice()) {
            return err('设备类型不正确！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        $chargerID = request::int('chargerID');

        $charging_data = $device->fuelingNOWData($chargerID);
        if (!empty($charging_data)) {
            return err('设备正在使用中！');
        }

        $price = intval(round(request::float('price', 0, 2) * 100));
        if ($price < 1) {
            return err('付款金额不正确！');
        }

        $serial = Order::makeSerial($user);

        return Helper::createFuelingOrder($user, $device, $chargerID, $price, $serial);
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

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $keywords = request::trim('keywords');
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
        $serial = request::str('serial');
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