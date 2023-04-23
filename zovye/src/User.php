<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
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
    const PSEUDO = -1;      //虚拟用户

    const WX = 0;           //微信H5用户
    const WxAPP = 1;        //微信小程序用户
    const ALI = 2;          //阿里支付宝用户
    const DouYin = 3;       //抖音用户
    const API = 10;         //API用户
    const THIRD_ACCOUNT = 15;   //第三方公众号
    const PROMO = 20;       //PROMO 用户

    const UNKNOWN = 0;
    const MALE = 1;
    const FEMALE = 2;

    const ORDER_ACCOUNT_LOCKER = 'account::order';
    const ORDER_LOCKER = 'order';
    const COMMISSION_BALANCE_LOCKER = 'commission::balance';
    const BALANCE_LOCKER = 'balance';
    const TASK_LOCKER = 'task';
    const DAILY_SIGN_IN_LOCKER = 'balance:daily:sign_in';
    const BALANCE_GIVE_LOCKER = 'balance:give';
    const CHARGING_LOCKER = 'charging';
    const FUELING_LOCKER = 'fueling';

    const API_USER_HEAD_IMG = MODULE_URL."static/img/api.svg";

    public static function getTableName(): string
    {
        return m('user')->getTableName();
    }

    public static function objClassname(): string
    {
        return m('user')->objClassname();
    }

    public static function isSnapshot(): bool
    {
        return boolval($_SESSION['is_snapshotuser']);
    }

    public static function isSubscribed(userModelObj $user): bool
    {
        $res = Wx::getWxAccount()->fansQueryInfo($user->getOpenid());
        return $res && $res['subscribe'];
    }

    public static function getUserCharacter($obj): array
    {
        static $data = [
            'ali' => [
                'id' => self::ALI,
                'name' => 'ali',
                'title' => '支付宝',
                'color' => '#1296DB',
                'icon' => MODULE_URL."static/img/alipay.jpg",
            ],
            'wxapp' => [
                'id' => self::WxAPP,
                'name' => 'wxapp',
                'title' => '微信小程序',
                'color' => '#00d410',
                'icon' => MODULE_URL."static/img/wxapp.jpg",
            ],
            'wx' => [
                'id' => self::WX,
                'name' => 'wx',
                'title' => '微信公众号',
                'color' => '#4CAF50',
                'icon' => MODULE_URL."static/img/wxpay.jpg",
            ],
            'api' => [
                'id' => self::API,
                'name' => 'api',
                'title' => '第三方API用户',
                'color' => '#4CAF50',
                'icon' => MODULE_URL."static/img/api.svg",
            ],
            'third' => [
                'id' => self::THIRD_ACCOUNT,
                'name' => 'api',
                'title' => '第三方公众号授权用户',
                'color' => '#4CAF50',
                'icon' => MODULE_URL."static/img/third.svg",
            ],
            'douyin' => [
                'id' => self::DouYin,
                'name' => 'douyin',
                'title' => '抖音',
                'color' => '#4CAF50',
                'icon' => MODULE_URL."static/img/douyin.svg",
            ],
            'promo' => [
                'id' => self::PROMO,
                'name' => 'promo',
                'title' => '推广用户',
                'color' => '#ff9800',
                'icon' => MODULE_URL."static/img/promo.svg",
            ],
            'pseudo' => [
                'id' => self::PSEUDO,
                'name' => 'pseudo',
                'title' => '虚拟用户',
                'color' => '#9e9e9e',
                'icon' => MODULE_URL."static/img/random.svg",
            ],
        ];

        if (self::isAliUser($obj)) {
            return $data['ali'];
        } elseif (self::isWXAppUser($obj)) {
            return $data['wxapp'];
        } elseif (self::isApiUser($obj)) {
            return $data['api'];
        } elseif (self::isThirdAccountUser($obj)) {
            return $data['third'];
        } elseif (self::isDouYinUser($obj)) {
            return $data['douyin'];
        } elseif (self::isPromoUser($obj)) {
            return $data['promo'];
        } elseif (self::isPseudoUser($obj)) {
            return $data['pseudo'];
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

    public static function isThirdAccountUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isThirdAccountUser();
        }
        $user = User::get($obj, is_string($obj));

        return $user && $user->isThirdAccountUser();
    }

    public static function isPromoUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isPromoUser();
        }

        $user = User::get($obj, is_string($obj));
        return $user && $user->isPromoUser();
    }

    public static function isPseudoUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isPseudoUser();
        }

        $user = User::get($obj, is_string($obj));
        return $user && $user->isPseudoUser();
    }

    public static function isDouYinUser($obj): bool
    {
        if ($obj instanceof userModelObj) {
            return $obj->isDouYinUser();
        }

        $user = User::get($obj, is_string($obj));
        return $user && $user->isDouYinUser();
    }

    public static function getPseudoUser($openid = null, $nickname = '虚拟用户'): ?userModelObj
    {
        return User::getOrCreate($openid ?? App::uid(16), User::PSEUDO, [
            'nickname' => $nickname,
            'avatar' => MODULE_URL . 'static/img/unknown.svg',
        ]);
    }

    /**
     * @param $id
     * @param bool $is_openid
     * @param int|null $app
     * @return userModelObj|null
     */
    public static function get($id, bool $is_openid = false, int $app = null): ?userModelObj
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
            } else {
                $cond['id'] = intval($id);
            }
            $user = self::findOne($cond);
            if ($user) {
                $cache[$user->getId()] = $user;
                $cache[$user->getOpenid()] = $user;

                return $user;
            }
        }

        return null;
    }

    public static function getOrCreate($openid, $app = null, $extra = []): ?userModelObj
    {
        $user = self::get($openid, true, $app);
        if ($user) {
            return $user;
        }

        $data = array_merge($extra, [
            'openid' => $openid,
        ]);
        
        if (isset($app)) {
            $data['app'] = $app;
        }

        return self::create($data);
    }

    /**
     * @param mixed $condition
     * @return userModelObj|null
     */
    public static function findOne($condition = []): ?userModelObj
    {
        return self::query($condition)->findOne();
    }

    /**
     * @param mixed $condition
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
    public static function create(array $data = []): ?userModelObj
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

        //首单优惠
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
