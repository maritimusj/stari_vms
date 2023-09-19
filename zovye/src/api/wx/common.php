<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use Exception;
use zovye\App;
use zovye\domain\LoginData;
use zovye\domain\User;
use zovye\domain\WxApp;
use zovye\JSON;
use zovye\Log;
use zovye\model\agentModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\Util;
use zovye\We7;
use zovye\Wx;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

class common
{
    static $user = null;

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
        return Request::str('token');
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
                LoginData::USER,
            ];
        }

        $login_data = LoginData::get($token, $src);

        if (empty($login_data)) {
            JSON::fail('请先登录后再请求数据！[102]');
        }

        if ($login_data->getSrc() == LoginData::KEEPER) {
            $keeper = \zovye\domain\Keeper::get($login_data->getUserId());
            if (empty($keeper)) {
                JSON::fail('请先登录后再请求数据！[103]');
            }
            self::$user = $keeper->getUser();

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
     * 获取当前已登录的用户.
     */
    public static function getAgentOrPartner(): agentModelObj
    {
        $user = self::getUser();

        if (!$user->isAgent() && !$user->isPartner()) {
            JSON::fail('您还不是我们的代理商，请联系我们！');
        }

        return $user->agent();
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
        $keeper = \zovye\domain\Keeper::findOne(['id' => $login_data->getUserId()]);

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
    public static function checkCurrentUserPrivileges(agentModelObj $agent, $fn, bool $get_result = false): bool
    {
        $commission_state = App::isCommissionEnabled() && $agent->isCommissionEnabled();

        $funcs = $agent->settings('agentData.funcs');
        $res = common::checkEnabledFunctions($funcs, $fn, function ($name) use ($commission_state) {
            if ($name == 'F_cm') {
                return $commission_state;
            } else {
                return 'n/a';
            }
        }
        );

        if ($get_result) {
            return $res;
        } else {
            if (!$res) {
                JSON::fail('没有权限访问这个功能，请联系管理员！');
            }
        }

        return false;
    }

    public static function getUserBank(userModelObj $user): array
    {
        return $user->settings(
            'agentData.bank',
            [
                'realname' => '',
                'bank' => '',
                'branch' => '',
                'account' => '',
                'address' => [
                    'province' => '',
                    'city' => '',
                ],
            ]
        );
    }

    public static function setUserBank(userModelObj $user): array
    {
        $bankData = [
            'realname' => Request::trim('realname'),
            'bank' => Request::trim('bank'),
            'branch' => Request::trim('branch'),
            'account' => Request::trim('account'),
            'address' => [
                'province' => Request::trim('province'),
                'city' => Request::trim('city'),
            ],
        ];

        $result = $user->updateSettings('agentData.bank', $bankData);

        return $result ? ['msg' => '保存成功！'] : err('保存失败！');
    }

    public static function getUserQRCode(userModelObj $user): array
    {
        $user_qrcode = $user->settings('qrcode', []);
        if (isset($user_qrcode['wx'])) {
            $user_qrcode['wx'] = Util::toMedia($user_qrcode['wx']);
        }
        if (isset($user_qrcode['ali'])) {
            $user_qrcode['ali'] = Util::toMedia($user_qrcode['ali']);
        }

        return (array)$user_qrcode;
    }

    public static function updateUserQRCode(userModelObj $user, $type): array
    {
        We7::load()->func('file');
        $res = We7::file_upload($_FILES['pic']);

        if (!is_error($res)) {
            $filename = $res['path'];
            if ($res['success'] && $filename) {
                try {
                    We7::file_remote_upload($filename);
                } catch (Exception $e) {
                    Log::error('doPageUserQRcode', $e->getMessage());
                }
            }

            $user_qrcode = $user->settings('qrcode', []);
            $user_qrcode[$type] = $filename;

            if ($user->updateSettings('qrcode', $user_qrcode)) {
                return ['status' => 'success', 'msg' => '上传成功！'];
            }
        }

        return err('上传失败！');
    }
}