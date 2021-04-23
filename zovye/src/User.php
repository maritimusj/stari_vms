<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\userModelObj;
use zovye\base\modelObjFinder;

/**
 * Class User
 * @package zovye
 */
class User
{
    const WX = 0;
    const WxAPP = 1;
    const ALI = 2;

    const API = 10;   

    const UNKNOWN = 0;
    const MALE = 1;
    const FEMALE = 2;

    const AGENT = 'agent';
    const PARTNER = 'partner';
    const KEEPER = 'keeper';
    const GSPOR = 'gspor';
    const TESTER = 'tester';

    const API_USER_HEAD_IMG = MODULE_URL . "static/img/api.svg";


    public static function getTableName(): string
    {
        return m('user')->getTableName();
    }

    public static function objClassname(): string
    {
        return m('user')->objClassname();
    }

    public static function getUserCharacter($obj): array
    {
        static $data = [
            'ali' => [
                'name' => 'ali',
                'title' => '支付宝',
                'color' => '#1296DB',
                'icon' => MODULE_URL . "static/img/alipay.jpg",
            ],
            'wxapp' => [
                'name' => 'wxapp',
                'title' => '小程序',
                'color' => '#00d410',
                'icon' => MODULE_URL . "static/img/wxapp.jpg",
            ],
            'wx' => [
                'name' => 'wx',
                'title' => '微信',
                'color' => '#4CAF50',
                'icon' => MODULE_URL . "static/img/wxpay.jpg",
            ],
            'api' => [
                'name' => 'api',
                'title' => '第三方API用户',
                'color' => '#4CAF50',
                'icon' => MODULE_URL . "static/img/api.svg",
            ]
        ];

        if (self::isAliUser($obj)) {
            return $data['ali'];
        } elseif (self::isWXAppUser($obj)) {
            return $data['wxapp'];
        } else if (self::isApiUser($obj)) {
            return $data['api'];
        }

        return $data['wx'];
    }

    public static function isAliUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isAliUser();
        }

        $user = User::get($obj, is_string($obj));
        return $user && $user->isAliUser();
    }

    public static function isWxUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isWxUser();
        }
        $user = User::get($obj, is_string($obj));
        return $user && $user->isWxUser();
    }

    public static function isWXAppUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isWXAppUser();
        }
        $user = User::get($obj, is_string($obj));
        return $user && $user->isWXAppUser();
    }

    public static function isApiUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isApiUser();
        }
        $user = User::get($obj, is_string($obj));
        return $user && $user->isApiUser();
    }
    /**
     * @param $id
     * @param bool $is_openid
     * @param int $app
     * @return userModelObj|null
     */
    public static function get($id, $is_openid = false, $app = null): ?userModelObj
    {
        /** @var userModelObj[] $cache */
        static $cache = [];
        if ($id) {
            if ($cache[$id]) {
                return $cache[$id];
            }
            $cond = [];
            if (isset($app)) {
                $cond['app'] = $app;
            }
            if ($is_openid) {
                $cond['openid'] = strval($id);
                $user = self::findOne($cond);
            } else {
                $cond['id'] = intval($id);
                $user = self::findOne($cond);
            }
            if ($user) {
                $cache[$user->getId()] = $user;
                $cache[$user->getOpenid()] = $user;
                return $user;
            }
        }

        return null;
    }

    /**
     * @param array $condition
     * @return userModelObj|null
     */
    public static function findOne($condition = []): ?userModelObj
    {
        return self::query($condition)->findOne();
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query($condition = []): modelObjFinder
    {
        return m('user')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param array $data
     * @return userModelObj|null
     */
    public static function create($data = []): ?userModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        return m('user')->create($data);
    }

    public static function getUserDiscount(userModelObj $user, $goods_data, $total = 1): int
    {
        //暂时未用到 $total
        unset($total);

        if (empty($goods_data['id']) || empty($goods_data['price'])) {
            return 0;
        }

        $discount = 0;

        if (empty(Order::getLastOrderOfUser($user))) {
            if (!empty($goods_data['discountPrice'])) {
                if ($goods_data['price'] > $goods_data['discountPrice']) {
                    $discount = intval($goods_data['discountPrice']);
                }
            } else {
                $system_discount = intval(settings('user.discountPrice'));
                if ($goods_data['price'] > $system_discount) {
                    $discount = $system_discount;
                }
            }
        }

        return $discount;
    }
}
