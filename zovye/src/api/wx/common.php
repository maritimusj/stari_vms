<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\model\agentModelObj;
use zovye\App;
use zovye\request;
use zovye\JSON;
use zovye\model\keeperModelObj;
use zovye\LoginData;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Wx;
use zovye\WxApp;
use function zovye\_W;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

class common
{
    public static function getDecryptedWxUserData(): array
    {
        $code = request::str('code');
        if (empty($code)) {
            return err('缺少必要的请求参数！');
        }

        $config = settings('agentWxapp', []);

        $vendorUID = request::trim('vendor');
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

        $iv = request::str('iv');
        $encrypted_data = request::str('encryptedData');

        $result = Wx::decodeWxAppData($code, $iv, $encrypted_data, $config);
        if (is_error($result)) {
            return $result;
        }

        $result['config'] = $config;
        return $result;
    }

    public static function getToken(): string
    {
        return request::str('token');
    }

    public static function getUser(): userModelObj
    {
        static $user = null;

        if ($user) {
            return $user;
        }

        if (empty($token)) {
            $token = common::getToken();
        }

        if (empty($token)) {
            JSON::fail('请先登录后再请求数据！[101]');
        }

        $login_data = LoginData::get($token, [
            LoginData::AGENT,
            LoginData::AGENT_WEB,
            LoginData::KEEPER,
            LoginData::USER,
        ]);

        if (empty($login_data)) {
            JSON::fail('请先登录后再请求数据！[102]');
        }

        if ($login_data->getSrc() == LoginData::KEEPER) {
            $keeper = \zovye\Keeper::get($login_data->getUserId());
            if (empty($keeper)) {
                JSON::fail('请先登录后再请求数据！[103]');
            }
            $user = $keeper->getUser();

        } else {
            $user = User::get($login_data->getUserId());
        }

        if (empty($user)) {
            JSON::fail('请先登录后再请求数据！[104]');
        }

        if ($user->isBanned()) {
            $login_data->destroy();
            JSON::fail('暂时无法使用，请联系管理员！');
        }

        return $user;
    }

    /**
     * 获取当前已登录的用户.
     *
     *
     * @return agentModelObj
     */
    public static function getAgent(): agentModelObj
    {
        $user = self::getUser();

        if (!$user->isAgent() && !$user->isPartner()) {
            JSON::fail('您还不是我们的代理商，请联系我们！');
        }

        return $user->agent();
    }

    public static function getAgentOrKeeper()
    {
        if (empty($token)) {
            $token = common::getToken();
        }

        $keeper = null;

        $login_data = LoginData::get($token, [LoginData::AGENT, LoginData::AGENT_WEB]);
        if (empty($login_data)) {
            $login_data = LoginData::get($token, LoginData::KEEPER);
            if (empty($login_data)) {
                JSON::fail('请先登录后再请求数据![202]');
            }

            /** @var keeperModelObj $keeper */
            $keeper = \zovye\Keeper::findOne(['id' => $login_data->getUserId()]);
        } else {
            $user = User::get($login_data->getUserId());
        }

        if (empty($keeper) && empty($user)) {
            JSON::fail('请先登录后再请求数据！[203]');
        }

        if (empty($user)) {
            $user = $keeper;
        }

        return $user;
    }

    /**
     * 生成用户的GUID.
     *
     * @param userModelObj|null $target
     *
     * @return string
     */
    public static function getGUID(userModelObj $target = null): string
    {
        $id = $target ? $target->getId() : false;

        $session_key = '';

        $login_data = LoginData::get(common::getToken());
        if ($login_data) {
            $session_key = $login_data->getSessionKey();
        }

        if (empty($session_key)) {
            $session_key = _W('token');
        }

        return sha1("$session_key$id");
    }

    /**
     * @param $assign_data
     * @param array|string $fn
     * @param callable|null $cb
     *
     * @return bool
     */
    public static function checkFuncs($assign_data, $fn, callable $cb = null): bool
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
     * 代理商功能权限检查，没有设置禁止时，默认为允许.
     *
     * @param $fn
     * @param bool $get_result
     *
     * @return bool
     */
    public static function checkCurrentUserPrivileges($fn, bool $get_result = false): bool
    {
        $user = common::getAgent();

        $commission_state = App::isCommissionEnabled() && $user->isCommissionEnabled();

        $funcs = $user->settings('agentData.funcs');
        $res = common::checkFuncs($funcs, $fn, function ($name) use ($commission_state) {
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

    /**
     * @param userModelObj $user
     *
     * @return array
     */
    public static function setUserBank(userModelObj $user): array
    {
        $bankData = [
            'realname' => request::trim('realname'),
            'bank' => request::trim('bank'),
            'branch' => request::trim('branch'),
            'account' => request::trim('account'),
            'address' => [
                'province' => request::trim('province'),
                'city' => request::trim('city'),
            ],
        ];

        $user->updateSettings('agentData.bank', $bankData);

        return ['msg' => '保存成功！'];
    }
}