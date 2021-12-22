<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxweb;

use zovye\Account;
use zovye\Helper;
use zovye\Job;
use zovye\JSON;
use zovye\model\balanceModelObj;
use zovye\Order;
use zovye\User;
use zovye\Util;
use zovye\Device;
use zovye\request;
use zovye\api\wxx\common;
use zovye\App;
use zovye\Balance;
use zovye\Log;

use function zovye\err;
use function zovye\is_error;
use function zovye\m;

class api
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
            Log::error('wxweb', $res);
            return err('用户登录失败，请稍后再试！[103]');
        }

        //如果小程序请求中携带了H5页面的openid，则使用该openid的H5用户登录小程序
        if (request::has('openId')) {
            $res['openId'] = request::str('openId');
        }

        return common::doUserLogin($res, request::array('userInfo', []));
    }

    /**
     * 获取设备相关的广告
     * @return array
     */
    public static function advs(): array
    {
        $type = request::int('typeId');
        $num = request::int('num', 10);

        if (request::has('deviceId')) {
            $device = Device::get(request::str('deviceId'), true);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
        }

        return Util::getDeviceAdvs($device ?? Device::getDummyDevice(), $type, $num);
    }

    public static function accounts(): array
    {
        $user = \zovye\api\wx\common::getUser();

        if (request::has('deviceId')) {
            $device = Device::get(request::str('deviceId'), true);
            if (empty($device)) {
                return err('找不到这个设备！');
            }            
        } else {
            $device = Device::getDummyDevice();
        }

        $include = [];
        if (request::bool('balance')) {
            $include[] = Account::BALANCE;
        }

        if (request::bool('commission')) {
            $include[] = Account::COMMISSION;
        }

        if (empty($include)) {
            return [];
        }

        $params = [
            'type' => [Account::VIDEO],
            's_type' => [],
            'include' => $include,
        ];

        if (request::has('max')) {
            $params['max'] = request::int('max');
        }

        return Account::getAvailableList($device, $user, $params);
    }

    public static function goods(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $type = request::str('type'); //free or pay or balance

        if ($type == 'balance') {
            $result = $device->getGoodsList($user, ['balance']);
        } elseif ($type == 'free') {
            $result = $device->getGoodsList($user, ['allowFree']);
        } else {
            $result = $device->getGoodsAndPackages($user, ['allowPay']);
        }

        return $result;
    }

    public static function get(): array
    {
        $user = \zovye\api\wx\common::getUser();

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            JSON::fail('无法锁定用户，请稍后再试！');
        }

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        $account = Account::findOneFromUID(request::str('uid'));
        if (empty($account)) {
            return err('找不到指定任务！');
        }

        $goods_id = request::int('goodsId');
        if (empty($goods_id)) {
            $goods = $device->getGoodsByLane(0);
            if ($goods && $goods['num'] < 1) {
                $goods = $device->getGoods($goods['id']);
            }
        } else {
            $goods = $device->getGoods($goods_id);
        }

        if (empty($goods)) {
            return err('找不到商品！');
        }

        if ($goods['num'] < 1) {
            return err('商品数量不足！');
        }

        $orderUID = Order::makeUID($user, $device);

        if (Job::createAccountOrder([
            'device' => $device->getId(),
            'user' => $user->getId(),
            'account' => $account->getId(),
            'goods' => $goods['id'],
            'orderUID' => $orderUID,
            'ip' => Util::getClientIp(),
        ])) {
            return ['orderUID' => $orderUID];
        }

        return err('请求出货失败！');
    }

    public static function exchange(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $device_uid = request::str('deviceId');
        $goods_id = request::int('goodsId');
        $num = request::int('num');

        $res = Helper::exchange($user, $device_uid, $goods_id, $num);
        if (is_error($res)) {
            JSON::fail($res);
        }

        return ['orderUID' => $res];
    }

    public static function pay(): array
    {
        $user = \zovye\api\wx\common::getUser();

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        if ($device->isLocked()) {
            return err('设备正忙，请稍后再试！');
        }

        $is_package = false;
        if (request::has('goodsId')) {
            $goods_or_package_id = request::int('goodsId');
            if (empty($goods_or_package_id)) {
                return err('没有指定商品！');
            }
            $num = min(App::orderMaxGoodsNum(), max(request::int('num'), 1));
            if ($num < 1) {
                return err('购买数量不能小于1！');
            }
        } else {
            $goods_or_package_id = request::int('packageId');
            if (empty($goods_or_package_id)) {
                return err('没有指定套餐！');
            }
            $is_package = true;
        }

        return Helper::createWxAppOrder($user, $device, $goods_or_package_id, $num ?? 1, $is_package);
    }

    public static function orderStatus(): array
    {
        $order = Order::get(request::str('uid'), true);
        if (empty($order)) {
            return [
                'msg' => '正在查询订单',
                'code' => 100,
            ];
        }

        $errno = $order->getExtraData('pull.result.errno', -1);

        if ($errno == 0) {
            return [
                'code' => 200,
                'msg' => '出货完成!',
            ];
        } elseif ($errno == 12) {
            return [
                'code' => 100,
                'msg' => '订单正在处理中，请稍等！',
            ];
        } elseif ($errno == -1) {
            return [
                'msg' => '订单正在处理中',
                'code' => 100,
            ];
        }

        return [
            'code' => 502,
            'msg' => '出货失败！',
        ];
    }

    public static function userInfo(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $data = $user->profile();
        $data['banned'] = $user->isBanned();

        if (App::isBalanceEnabled()) {
            $data['signed'] = $user->isSigned();
            $data['balance'] = $user->getBalance()->total();
        }

        return $data;
    }

    public static function feedback(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $imei = request::str('deviceId');

        $text = request::str('text');
        $pics = request::array('pics');

        $device = Device::get($imei, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

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

        return err('反馈失败！');
    }

    public static function signIn(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $res = Balance::dailySignIn($user);
        if (is_error($res)) {
            return $res;
        }
        
        return [
            'balance' => $user->getBalance()->total(),
            'bonus' => $res,
        ];
    }

    public static function bonus(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $account = Account::findOneFromUID(request::str('uid'));
        if (empty($account)) {
            return err('找不到这个公众号！');
        }

        $result = Balance::give($user, $account);
        if (is_error($result)) {
            return err($result);
        }

        return [
            'balance' => $user->getBalance()->total(),
            'bonus' => $result instanceof balanceModelObj ? $result->getXVal() : 0,
        ];
    }

    public static function balanceLog(): array 
    {
        $user = \zovye\api\wx\common::getUser();
        
        $query = $user->getBalance()->log();
        if (request::has('lastId')) {
            $query->where(['id <' => request::int('lastId')]);
        }

        $query->limit(request::int('pagesize', DEFAULT_PAGE_SIZE));
        $query->orderBy('id DESC');

        $result = [];
        foreach($query->findAll() as $entry) {
            $result[] = Balance::format($entry);
        }

        return $result;
    }

    public static function orderList(): array
    {
        $user = \zovye\api\wx\common::getUser();
        
        $way = request::str('way');
        $page = request::int('page');
        $page_size = request::int('pagesize');
    
        return Order::getList($user, $way, $page, $page_size);
    }
}
