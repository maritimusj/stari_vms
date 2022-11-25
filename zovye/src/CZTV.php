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

        $token = request::str('token');
        if (empty($token)) {
            if ($config['redirect_url']) {
                Util::redirect($config['redirect_url']);
            } else {
                return false;
            }
        }

        $user = self::getUser($config, $token);
        if (empty($user) || is_error($user)) {
            Util::resultAlert('找不到用户，请重试！', 'error');
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
        }

        app()->cztvPage($device);

        return true;
    }

    public static function getUser($config, $token)
    {
        $response = Util::get(self::API_URL, 3, [
            'appId' => $config['appid'],
            'token' => $token,
        ], true);

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
}