<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\Device;
use zovye\request;
use function zovye\err;

class fueling
{
    /**
     * 设备详情
     */
    public static function deviceDetail()
    {

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
    public static function status()
    {
        $serial = request::str('serial');
        return \zovye\Fueling::orderStatus($serial);
    }

    /**
     * 订单列表
     */
    public static function orderList()
    {

    }

    /**
     * 订单详情
     */
    public static function orderDetail()
    {

    }
}