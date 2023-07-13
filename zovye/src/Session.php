<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use ali\aop\AopClient;
use ali\aop\request\AlipaySystemOauthTokenRequest;
use ali\aop\request\AlipayUserInfoShareRequest;
use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use WeAccount;

class Session
{
    /**
     * 获取当前用户信息.
     *
     * @param array $params
     *
     * @return userModelObj|null
     */
    public static function getCurrentUser(array $params = []): ?userModelObj
    {
        $user = null;
        if (App::isAliUser() || App::isDouYinUser()) {
            $user = User::get(App::getUserUID(), true);
        } else {
            if (empty($user)) {
                $update = !empty($params['update']);
                $fans = self::fansInfo($update);
                if ($fans && !empty($fans['openid'])) {
                    $user = User::get($fans['openid'], true);
                    if (empty($user) && !empty($params['create'])) {
                        $data = [
                            'app' => User::WX,
                            'nickname' => strval($fans['nickname'] ?? '匿名用户'),
                            'avatar' => strval($fans['headimgurl']),
                            'openid' => strval($fans['openid']),
                        ];

                        $user = User::create($data);
                        if ($user) {
                            //创建云众商城关联
                            if (isset($params['yzshop']) && YZShop::isInstalled()) {
                                $yz_shop = $params['yzshop'];
                                $agent = null;
                                if (isset($yz_shop['agent']) && $yz_shop['agent'] instanceof userModelObj) {
                                    $agent = $yz_shop['agent'];
                                } elseif (isset($yz_shop['device']) && $yz_shop['device'] instanceof deviceModelObj) {
                                    $agent = $yz_shop['device']->getAgent();
                                }

                                if ($agent) {
                                    YZShop::create($user, $agent);
                                }
                            }

                            if (!empty($params['from'])) {
                                $user->set('fromData', $params['from']);
                            }
                            $user->set('fansData', $fans);
                            if (isset($params['update'])) {
                                $update = false;
                            }

                            if (App::isBalanceEnabled()) {
                                Balance::onUserCreated($user);
                            }
                        }
                    }

                    if ($user) {
                        if ($update) {
                            if ($user->getNickname() != $fans['nickname']) {
                                $user->setNickname($fans['nickname']);
                            }
                            if ($user->getAvatar() != $fans['headimgurl']) {
                                $user->setAvatar($fans['headimgurl']);
                            }
                            $customData = $user->get('customData', []);
                            $user->set('fansData', array_merge($fans, $customData));
                            $user->save();
                        }
                        App::setContainer($user);
                    }
                }
            }
        }

        return $user;
    }


    /**
     * 获取fans数据.
     *
     * @param bool $update
     * @return array
     */
    public static function fansInfo(bool $update = false): array
    {
        $openid = _W('openid');
        if ($openid) {
            if ($update) {
                $userinfo = CacheUtil::cachedCall(6, function () use ($openid) {
                    $oauth_account = WeAccount::createByUniacid();
                    $userinfo = $oauth_account->fansQueryInfo($openid);
                    $userinfo['nickname'] = stripcslashes($userinfo['nickname']);
                    $userinfo['avatar'] = $userinfo['headimgurl'];

                    return $userinfo;
                }, $openid);

                //接口调用次数上限后，$userinfo中相关字段为空
                if (!empty($userinfo['nickname']) && !empty($userinfo['avatar'])) {
                    return $userinfo;
                }
            }
            $res = We7::mc_oauth_userinfo();
            if (!is_error($res)) {
                return $res;
            }
        }

        return [];
    }

    protected static function getAliUserSex($gender): int
    {
        switch ($gender) {
            case 'm':
                return 1;
            case 'f':
                return 2;
            default:
                return 0;
        }
    }

    /**
     * 简单获取用户手机系统类型.
     *
     * @return string
     */
    public static function getUserPhoneOS(): string
    {
        if (stripos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false) {
            return 'ios';
        }

        if (stripos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false) {
            return 'android';
        }

        return 'unknown';
    }

    /**
     * 获取返回js sdk字符串.
     *
     * @param bool $debug
     *
     * @return string
     */
    public static function fetchJSSDK(bool $debug = false): string
    {
        ob_start();

        We7::register_jssdk($debug);

        return ob_get_clean();
    }


    /**
     * 是否在支付宝APP中
     * @return bool
     */
    public static function isAliAppContainer(): bool
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if (empty($user_agent)) {
            return false;
        }
        $alipay_arr = ['aliapp', 'alipayclient', 'alipay'];
        foreach ($alipay_arr as $val) {
            if (strpos($user_agent, $val) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function isDouYinAppContainer(): bool
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if (empty($user_agent)) {
            return false;
        }
        $douyin = ['bytedancewebview'];
        foreach ($douyin as $val) {
            if (strpos($user_agent, $val) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getAliUser(string $code, deviceModelObj $device = null): ?userModelObj
    {
        try {
            $aop = new AopClient();
            $aop->appId = settings('ali.appid');
            $aop->rsaPrivateKey = settings('ali.prikey');
            $aop->alipayrsaPublicKey = settings('ali.pubkey');

            $request = new AlipaySystemOauthTokenRequest();
            $request->setGrantType('authorization_code');
            $request->setCode($code);

            $result = $aop->execute($request);

            if ($result->error_response) {
                throw new RuntimeException('获取用户信息失败：'.$result->error_response->sub_msg);
            }

            //access_token;
            $access_token = $result->alipay_system_oauth_token_response->access_token;

            //获取用户信息
            $request = new AlipayUserInfoShareRequest();
            $result = $aop->execute($request, $access_token);

            if ($result->alipay_user_info_share_response->code !== '10000') {
                throw new RuntimeException('获取用户信息失败：'.$result->alipay_user_info_share_response->sub_msg);
            }

            Log::debug('ali', $result->alipay_user_info_share_response);

            $ali_user_id = $result->alipay_user_info_share_response->user_id;
            $nick_name = $result->alipay_user_info_share_response->nick_name;
            $avatar = $result->alipay_user_info_share_response->avatar;

            $user = User::get($ali_user_id, true, User::ALI);
            if (empty($user)) {
                $data = [
                    'app' => User::ALI,
                    'nickname' => $nick_name,
                    'avatar' => $avatar,
                    'openid' => $ali_user_id,
                ];

                $user = User::create($data);
                if (!empty($device)) {
                    $params['from'] = [
                        'src' => 'device',
                        'device' => [
                            'name' => $device->getName(),
                            'imei' => $device->getImei(),
                        ],
                        'ip' => CLIENT_IP,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    ];

                    $user->set('fromData', $params['from']);
                }
            }

            if ($user) {
                $user->setNickname($nick_name);
                $user->setAvatar($avatar);
                $user->set('fansData', [
                    'province' => $result->alipay_user_info_share_response->province,
                    'city' => $result->alipay_user_info_share_response->city,
                    'sex' => self::getAliuserSex($result->alipay_user_info_share_response->gender),
                ]);
                $user->save();
                App::setContainer($user);
            }

            return $user;
        } catch (Exception $e) {
            Log::error('error', [
                'msg' => '获取支付宝用户失败！',
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public static function getDouYinUser($code, $device = null)
    {
        $douyin = DouYin::getInstance();

        $result = $douyin->getAccessToken($code);
        if (is_error($result)) {
            return $result;
        }

        $info = $douyin->getUserInfo($result['access_token'], $result['open_id']);
        if (is_error($info)) {
            return $info;
        }

        $user = User::get($info['open_id'], true, User::DouYin);
        if (empty($user)) {
            $data = [
                'app' => User::DouYin,
                'nickname' => $info['nickname'],
                'avatar' => $info['avatar'],
                'openid' => $info['open_id'],
            ];

            $user = User::create($data);
            if (!empty($device)) {
                $params['from'] = [
                    'src' => 'device',
                    'device' => [
                        'name' => $device->getName(),
                        'imei' => $device->getImei(),
                    ],
                    'ip' => CLIENT_IP,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                ];

                $user->set('fromData', $params['from']);
            }
        }

        if ($user) {
            $user->setNickname($info['nickname']);
            $user->setAvatar($info['avatar']);

            $result['updatetime'] = time();
            $user->set('douyin_token', $result);

            $user->set('fansData', [
                'province' => $info['province'],
                'city' => $info['city'],
                'sex' => $info['gender'],
            ]);
            $user->save();
            App::setContainer($user);
        }

        return $user;
    }
}