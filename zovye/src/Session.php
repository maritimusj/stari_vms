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
use WeAccount;
use zovye\business\DouYin;
use zovye\domain\Balance;
use zovye\domain\User;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\CacheUtil;

class Session
{
    /**
     * 获取当前用户信息
     */
    public static function getCurrentUser(array $params = []): ?userModelObj
    {
        static $user = null;
        if (self::isAliUser() || self::isDouYinUser()) {
            $user = User::get(self::getUserUID(), true);
        } else {
            if (empty($user)) {
                $update = boolval($params['update']);
                $fans = self::fansInfo($update);
                if ($fans && !empty($fans['openid'])) {
                    $user = User::get($fans['openid'], true);
                    if (empty($user) && !empty($params['create'])) {
                        $data = [
                            'app' => Session::isSnapshot() ? User::PSEUDO : User::WX,
                            'nickname' => strval($fans['nickname']),
                            'avatar' => strval($fans['headimgurl']),
                            'openid' => strval($fans['openid']),
                        ];

                        $user = User::create($data);
                        if ($user) {
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
                            //用户从屏幕授权公众号创建时，需要更改回普通公众号用户
                            if ($user->isThirdAccountUser()) {
                                $user->setApp(User::WX);
                            }
                            if ($user->getNickname() != $fans['nickname']) {
                                $user->setNickname($fans['nickname']);
                            }
                            if ($user->getAvatar() != $fans['headimgurl']) {
                                $user->setAvatar($fans['headimgurl']);
                            }
                            $user->save();
                        }
                        if (!Session::isSnapshot()) {
                            self::setContainer($user);
                        }
                    }
                }
            }
        }

        return $user;
    }


    /**
     * 获取fans数据
     */
    public static function fansInfo(bool $update = false): array
    {
        $openid = _W('openid');
        if (!$openid) {
            return [];
        }

        if (!$update) {
            return [
                'openid' => $openid,
            ];
        }

        $userinfo = (WeAccount::createByUniacid())->fansQueryInfo($openid);
        $userinfo['nickname'] = stripcslashes($userinfo['nickname']);
        $userinfo['avatar'] = $userinfo['headimgurl'];

        //接口调用次数上限后，$userinfo中相关字段为空
        if (!empty($userinfo['avatar'])) {
            return $userinfo;
        }

        return We7::mc_oauth_userinfo();
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

    public static function getClientIp(): string
    {
        return We7::get_ip();
    }

    public static function isSnapshot(): bool
    {
        return boolval($_SESSION['is_snapshotuser']);
    }

    public static function setContainer(userModelObj $user)
    {
        if ($user->isWxUser()) {
            $_SESSION['wx_user_id'] = $user->getOpenid();
        } elseif ($user->isWXAppUser()) {
            $_SESSION['wxapp_user_id'] = $user->getOpenid();
        } elseif ($user->isAliUser()) {
            $_SESSION['ali_user_id'] = $user->getOpenid();
        } elseif ($user->isDouYinUser()) {
            $_SESSION['douyin_user_id'] = $user->getOpenid();
        }
    }

    public static function getUserUID(): string
    {
        if (self::isWxUser()) {
            return strval($_SESSION['wx_user_id']);
        }

        if (self::isWxAppUser()) {
            return strval($_SESSION['wxapp_user_id']);
        }

        if (self::isAliUser()) {
            return strval($_SESSION['ali_user_id']);
        }

        if (self::isDouYinUser()) {
            return strval($_SESSION['douyin_user_id']);
        }

        return '';
    }

    public static function isAliUser(): bool
    {
        return !empty($_SESSION['ali_user_id']);
    }

    public static function isWxUser(): bool
    {
        return !empty($_SESSION['wx_user_id']);
    }

    public static function isWxAppUser(): bool
    {
        return !empty($_SESSION['wxapp_user_id']);
    }

    public static function isDouYinUser(): bool
    {
        return !empty($_SESSION['douyin_user_id']);
    }

    /**
     * 是否在支付宝APP中
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
                self::setContainer($user);
            }

            return $user;
        } catch (Exception $e) {
            Log::error('ali', [
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
            self::setContainer($user);
        }

        return $user;
    }
}