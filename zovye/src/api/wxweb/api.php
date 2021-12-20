<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxweb;

use zovye\Account;
use zovye\Util;
use zovye\State;
use zovye\Device;
use zovye\request;
use zovye\api\wxx\common;
use zovye\Log;

use function zovye\err;
use function zovye\is_error;

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

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        return Util::getDeviceAdvs($device, $type, $num);
    }

    public static function accounts(): array
    {
        $user = \zovye\api\wx\common::getUser();

        if (empty($user)) {
            return err('找不到这个用户！');
        }

        if ($user->isBanned()) {
            return err('用户暂时无法使用！');
        }

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
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

        $params =  [
            'type' => [Account::VIDEO],
            's_type' => [],
            'include' => $include,
        ];

        if (request::has('max')) {
            $params['max'] = request::int('max');
        }

        return Account::getAvailableList($device, $user, $params);
    }

    public static function goods():array
    {
        $user = \zovye\api\wx\common::getUser();

        if (empty($user)) {
            return err('找不到这个用户！');
        }

        if ($user->isBanned()) {
            return err('用户暂时无法使用！');
        }

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }
    
        $type = request::str('type'); //free or pay or balance
    
        if ($type == 'balance') {
            $result =  $device->getGoodsList($user, ['balance']);
        } elseif ($type == 'free') {
            $result = $device->getGoodsList($user, ['allowFree']);
        } else {
            $result = $device->getGoodsAndPackages($user, ['allowPay']);
        }
    
        return $result;
    }
}
