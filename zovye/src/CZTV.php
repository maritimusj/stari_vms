<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

class CZTV
{
    const API_URL = 'https://p.cztv.com/api/uc/getUserInfo';

    public static function handle($device_id): bool
    {
        if (empty($device_id)) {
            return false;
        }

        if (!App::isCZTVEnabled()) {
            return false;
        }

        $config = Config::cztv('client', []);
        if (empty($config['appid'])) {
            return false;
        }

        $token = request::str('sessionid');
        if (empty($token)) {
            if ($config['redirect_url']) {
                Util::redirect($config['redirect_url']);
            } else {
                return false;
            }
        }

        $user = self::getUser($config, $token);

        Log::debug("cztv", [
            'sessionid' => request::str('sessionid'),
            'config' => $config,
            'result' => is_error($user) ? $user : $user->profile(),
        ]);

        if (empty($user) || is_error($user)) {
            Util::resultAlert('无法获取用户信息，请重试！', 'error');
        }

        $device = Device::find($device_id, ['imei', 'shadow_id']);
        if (empty($device)) {
            Util::resultAlert('请重新扫描设备上的二维码！', 'error');
        }

        if ($device->isDown()) {
            Util::resultAlert('设备维护中，请稍后再试！', 'error');
        }

        $user->setLastActiveDevice($device);

        $account = Account::findOneFromUID($config['account_uid']);
        if ($account) {
            $res = Util::checkAvailable($user, $account,  $device);
            if (is_error($res)) {
                Util::resultAlert($res['message'], 'error');
            }
        } else {
            Util::resultAlert('没有关联公众号！', 'error');
        }

        app()->cztvPage($device, $user);

        return true;
    }

    public static function getUser($config, $token)
    {
        $url = self::API_URL . '?' . http_build_query([
            'appId' => $config['appid'],
            'token' => $token,
        ]);

        $response = Util::get($url, 3, [], true);

        if (is_error($response)) {
            return $response;
        }

        if (empty($response)) {
            return err('API接口返回空数据！');
        }

        if ($response['code'] != '200') {
            return err($response['msg'] ?? '发生错误，代码：' . $response['code']);
        }

        $data = $response['data'];
        if (isEmptyArray($data) || empty($data['wechatOpenId'])) {
            return err('API返回数据无效！');
        }

        $user= User::getOrCreate($data['wechatOpenId'], User::THIRD_ACCOUNT, [
            'app' => User::THIRD_ACCOUNT,
            'nickname' => $data['nickName'],
            'avatar' => $data['avatar'],
            'openid' => $data['wechatOpenId'],
            'mobile' => $data['mobile'],
        ]);

        if ($user) {
            $user->set('fansData', $data);
        }

        return $user;
    }

    public static function get(userModelObj $user, $device_uid, $goods_id, $num = 1, $order_no = '')
    {
        if (!App::isCZTVEnabled()) {
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
        if (empty($goods) || empty($goods[Goods::AllowFree])) {
            return err('无法领取这个商品，请联系管理员！');
        }

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $num = min(App::orderMaxGoodsNum(), max($num, 1));
        if ($num < 1) {
            return err('对不起，商品数量不正确！');
        }

        if ($goods['num'] < $num) {
            return err('对不起，商品数量不足！');
        }

        if (empty($order_no)) {
            $order_no = Order::makeUID($user, $device, sha1(REQUEST_ID));
        }

        $ip = $user->getLastActiveIp();

        $account = Account::findOneFromUID(config::cztv('client.account_uid'));
        if (empty($account)) {
            return err('没有关联公众号！');
        }

        if (Job::createAccountOrder([
            'account' => $account->getId(),
            'device' => $device->getId(),
            'user' => $user->getId(),
            'goods' => $goods['id'],
            'orderUID' => $order_no,
            'ip' => $ip,
        ])) {
            return ['order_uid' => $order_no];
        }

        return err('失败，请稍后再试！');
    }
}