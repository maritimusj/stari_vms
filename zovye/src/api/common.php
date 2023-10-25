<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api;

use zovye\App;
use zovye\domain\Balance;
use zovye\domain\Device;
use zovye\domain\Keeper;
use zovye\domain\LoginData;
use zovye\domain\User;
use zovye\domain\WxApp;
use zovye\JSON;
use zovye\Log;
use zovye\model\agentModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\Helper;
use zovye\util\TemplateUtil;
use zovye\util\Util;
use zovye\Wx;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

class common
{
    static $user = null;

    /**
     * 用户登录，小程序必须提交code,encryptedData和iv值
     */
    public static function login(): array
    {
        $res = common::getDecryptedWxUserData();
        if (is_error($res)) {
            Log::error('wxapi', $res);

            return err('用户登录失败，请稍后再试！[103]');
        }

        //如果小程序请求中携带了H5页面的openid，则使用该openid的H5用户登录小程序
        $h5_openid = '';
        if (Request::has('openId')) {
            $h5_openid = Request::str('openId');
        }

        return self::doUserLogin(
            $res,
            Request::array('userInfo'),
            $h5_openid,
            Request::str('device'),
            Request::str('from')
        );
    }

    public static function doUserLogin(
        $res,
        $user_info,
        $h5_openid = '',
        $device_uid = '',
        $ref_user_openid = ''
    ): array {
        $openid = strval($res['openId']);
        $user = User::get($openid, true);
        if (empty($user)) {
            $user = User::create([
                'app' => User::WxAPP,
                'openid' => $openid,
                'nickname' => $user_info['nickName'] ?? '',
                'avatar' => $user_info['avatarUrl'] ?? '',
                'mobile' => $res['phoneNumber'] ?? '',
                'createtime' => time(),
            ]);

            if (empty($user)) {
                return err('创建用户失败！');
            }

            if ($ref_user_openid) {
                $ref_user = User::get($ref_user_openid, true);
            }

            if (App::isBalanceEnabled()) {
                Balance::onUserCreated($user, $ref_user ?? null);
            }

        } else {
            if ($user_info['nickName']) {
                $user->setNickname($user_info['nickName']);
            }

            if ($user_info['avatarUrl']) {
                $user->setAvatar($user_info['avatarUrl']);
            }

            if ($res['phoneNumber']) {
                $user->setMobile($res['phoneNumber']);
            }

            $user->save();
        }

        $user->set('fansData', $user_info);

        if ($device_uid) {
            $device = Device::get($device_uid, true);
            if ($device) {
                $user->setLastActiveDevice($device);
                $user->setLastActiveData('from', 'wxapp');
            }
        }

        if ($h5_openid) {
            $user->updateSettings('customData.wx.openid', $h5_openid);
        } else {
            $h5_openid = $user->settings('customData.wx.openid', '');
        }

        if ($h5_openid) {
            $user = User::get($h5_openid, true, User::WX);
            if (empty($user)) {
                return err('没有找到关联的微信用户！');
            }
            if (isset($device)) {
                $user->setLastActiveDevice($device);
                $user->setLastActiveData('from', 'wxapp');
            }
            if ($res['phoneNumber']) {
                $user->setMobile($res['phoneNumber']);
                $user->save();
            }
        }

        if ($user->isBanned()) {
            return err('账户不可用，请稍后再试！');
        }

        //清除原来的登录信息
        foreach (LoginData::WxUser(['user_id' => $user->getId()])->findAll() as $entry) {
            $entry->destroy();
        }

        $token = Util::getTokenValue();

        $data = [
            'src' => LoginData::WX_APP_USER,
            'user_id' => $user->getId(),
            'session_key' => '',
            'openid_x' => $openid,
            'token' => $token,
        ];

        if (LoginData::create($data)) {
            return [
                'token' => $token,
            ];
        }

        return err('登录失败，请稍后再试！');
    }

    public static function getDecryptedWxUserData(): array
    {
        $code = Request::str('code');
        if (empty($code)) {
            return err('缺少必要的请求参数！');
        }

        $config = settings('agentWxapp', []);

        $vendorUID = Request::trim('vendor');
        if (!empty($vendorUID) && $vendorUID != 'v1' && $vendorUID != $config['key']) {
            $app = WxApp::get($vendorUID, true);
            if (empty($app)) {
                return err('找不到指定的小程序配置！');
            }
            $config = [
                'key' => $app->getKey(),
                'secret' => $app->getSecret(),
            ];
        }

        if (empty($config)) {
            return err('小程序配置为空！');
        }

        $iv = Request::str('iv');
        $encrypted_data = Request::str('encryptedData');

        $result = Wx::decodeWxAppData($code, $iv, $encrypted_data, $config);
        if (is_error($result)) {
            Log::error('wxapi', [
                'code' => $code,
                'iv' => $iv,
                'encrypted_data' => $encrypted_data,
                'config' => $config,
                'session_key' => $_SESSION['session_key'],
                'result' => $result,
            ]);

            return $result;
        }

        $result['config'] = $config;

        return $result;
    }

    public static function getToken(): string
    {
        $token = Request::str('token');
        if (empty($token)) {
            //兼容支付宝小程序
            $token = Request::str('user_id');
        }

        return $token;
    }

    public static function getWXAppUser(): ?userModelObj
    {
        $token = common::getToken();
        if (empty($token)) {
            JSON::fail('请先登录后再请求数据！[101]');
        }

        $login_data = LoginData::get($token);
        if (empty($login_data)) {
            JSON::fail('请先登录后再请求数据！[102]');
        }

        return User::get($login_data->getOpenidX(), true);
    }

    public static function getUser($src = null): userModelObj
    {
        if (self::$user) {
            return self::$user;
        }

        $token = common::getToken();

        if (empty($token)) {
            JSON::fail('请先登录后再请求数据！[101]');
        }

        if (empty($src)) {
            $src = [
                LoginData::AGENT,
                LoginData::AGENT_WEB,
                LoginData::KEEPER,
                LoginData::WX_APP_USER,
                LoginData::ALI_APP_USER,
            ];
        }

        $login_data = LoginData::get($token, $src);

        if (empty($login_data)) {
            JSON::fail('请先登录后再请求数据！[102]');
        }

        if ($login_data->getSrc() == LoginData::KEEPER) {
            $keeper = Keeper::get($login_data->getUserId());
            if (empty($keeper)) {
                JSON::fail('请先登录后再请求数据！[103]');
            }
            self::$user = $keeper->getUser();

        } elseif ($login_data->getSrc() == LoginData::WX_APP_USER) {
            self::$user = User::get($login_data->getUserId(), false, User::WxAPP);
        } elseif ($login_data->getSrc() == LoginData::ALI_APP_USER) {
            self::$user = User::get($login_data->getUserId(), false, User::ALI);
        } else {
            self::$user = User::get($login_data->getUserId());
        }

        if (empty(self::$user)) {
            JSON::fail('请先登录后再请求数据！[104]');
        }

        if (self::$user->isBanned()) {
            $login_data->destroy();
            JSON::fail('暂时无法使用，请联系管理员！');
        }

        return self::$user;
    }

    /**
     * 获取当前登录的代理商身份，如果当前登录用户是合伙人，则返回合伙人对应的代理商身份
     */
    public static function getAgent(bool $return_error = false): ?agentModelObj
    {
        $login_data = LoginData::get(common::getToken(), [LoginData::AGENT, LoginData::AGENT_WEB]);
        if (empty($login_data)) {
            if ($return_error) {
                return null;
            }
            JSON::fail('请先登录后再请求数据![202]');
        }

        self::$user = User::get($login_data->getUserId());
        if (empty(self::$user)) {
            if ($return_error) {
                return null;
            }
            JSON::fail('请先登录后再请求数据![203]');
        }

        if (self::$user->isAgent()) {
            return self::$user->getAgent();
        }

        if (self::$user->isPartner()) {
            return self::$user->getPartnerAgent();
        }

        if ($return_error) {
            return null;
        }

        JSON::fail('请先登录后再请求数据![204]');

        return null;
    }

    public static function getKeeper($return_error = false): ?keeperModelObj
    {
        $login_data = LoginData::get(common::getToken(), LoginData::KEEPER);
        if (empty($login_data)) {
            if ($return_error) {
                return null;
            }

            JSON::fail('请先登录后再请求数据![205]');
        }

        /** @var keeperModelObj $keeper */
        $keeper = Keeper::findOne(['id' => $login_data->getUserId()]);

        if (empty($keeper) && !$return_error) {
            JSON::fail('请先登录后再请求数据![206]');
        }

        return $keeper;
    }

    /**
     * 生成用户的GUID
     */
    public static function getGUID(userModelObj $target = null): string
    {
        $id = $target ? $target->getId() : false;

        $secret_key = self::getSecretKey();

        return sha1("$secret_key$id");
    }

    public static function getSecretKey(): string
    {
        return self::getToken();
    }

    public static function checkEnabledFunctions($assign_data, $fn, callable $cb = null): bool
    {
        //空$assign_data认为是拥有全部权限
        if (isset($assign_data)) {
            $fn = is_array($fn) ? $fn : explode(',', $fn);
            if ($fn) {
                foreach ($fn as $name) {
                    if (is_callable($cb)) {
                        $res = $cb($name);
                        if ($res === false) {
                            return false;
                        } elseif ($res === true) {
                            continue;
                        }
                    }

                    if (empty($assign_data[trim($name)])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * 代理商功能权限检查，没有设置禁止时，默认为允许
     */
    public static function checkPrivileges(agentModelObj $agent, $fn, bool $get_result = false): bool
    {
        $commission_state = App::isCommissionEnabled() && $agent->isCommissionEnabled();

        $funcs = $agent->settings('agentData.funcs');
        $res = common::checkEnabledFunctions($funcs, $fn, function ($name) use ($commission_state) {
            if ($name == 'F_cm') {
                return $commission_state;
            } else {
                return 'n/a';
            }
        });

        if ($get_result) {
            return $res;
        }

        if (!$res) {
            JSON::fail('没有权限访问这个功能，请联系管理员！');
        }

        return false;
    }

    /**
     * 获取设备相关的设置
     */
    public static function pageInfo(): array
    {
        $device_id = Request::str('device');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $result = TemplateUtil::getTplData();
        if ($device->isBlueToothDevice()) {
            $result['device'] = [
                'buid' => $device->getBUID(),
                'mac' => $device->getMAC(),
                'is_down' => $device->isMaintenance() ? 1 : 0,
            ];
        }
        $agent = $device->getAgent();
        if ($agent) {
            if ($agent->settings('agentData.misc.siteTitle') || $agent->settings('agentData.misc.siteLogo')) {
                $result['agent'] = [
                    'title' => $agent->settings('agentData.misc.siteTitle'),
                    'logo' => Util::toMedia($agent->settings('agentData.misc.siteLogo')),
                ];
            }
        }

        return $result;
    }

    public static function upload(userModelObj $user): array
    {
        unset($user);

        $res = Helper::upload('pic');
        if (is_error($res)) {
            return $res;
        }

        return ['data' => $res];
    }
}