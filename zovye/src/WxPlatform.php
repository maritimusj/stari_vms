<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use wx\Platform;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\Helper;
use zovye\util\HttpUtil;

class WxPlatform
{
    const AUTH_NOTIFY = 'notify';
    const AUTHORIZER_EVENT = 'event';
    const AUTH_REDIRECT_OP = 'authcode';

    const SUCCESS_RESPONSE = 'success';

    const SCOPE_SNS_API_BASE = 'snsapi_base';
    const SCOPE_SNS_API_USERINFO = 'snsapi_userinfo';

    const CREATE_AUTHORIZER_QRCODE_URL = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={TOKEN}';
    const SHOW_QRCODE_URL = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={TICKET}';

    const AUTHORIZATION_CODE_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid={APPID}&redirect_uri={REDIRECT_URI}&response_type=code&scope={SCOPE}&state={STATE}&component_appid={COMPONENT_APPID}#wechat_redirect';
    const ACCESS_TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/component/access_token?appid={APPID}&code={CODE}&grant_type=authorization_code&component_appid={COMPONENT_APPID}&component_access_token={COMPONENT_ACCESS_TOKEN}';
    const GET_USER_PROFILE = 'https://api.weixin.qq.com/sns/userinfo?access_token={ACCESS_TOKEN}&openid={OPENID}&lang=zh_CN';
    const GET_USER_PROFILE2 = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token={ACCESS_TOKEN}&openid={OPENID}&lang=zh_CN';

    const PERM_QRCODE = 'QR_LIMIT_STR_SCENE';
    const TEMP_QRCODE = 'QR_STR_SCENE';

    public static function getPlatform(): ?Platform
    {
        static $config = null;
        static $platform = null;

        if (isset($platform)) {
            return $platform;
        }

        if (!isset($config)) {
            $config = settings('account.wx.platform.config', []);
        }

        if (!isEmptyArray($config)) {
            $platform = new Platform($config);
        }

        return $platform;
    }

    /**
     * @param callable|null $fn
     */
    public static function parseEncryptedData(callable $fn = null): array
    {
        $platform = self::getPlatform();
        if ($platform) {
            $result = $platform->parseEncryptedData(
                Request::str('msg_signature'),
                Request::str('timestamp'),
                Request::str('nonce'),
                Request::raw()
            );

            Log::debug('wxplatform', $result);

            if (is_error($result)) {
                return $result;
            }

            if ($fn) {
                return $fn($result);
            }

            return ['message' => '成功！'];
        }

        return err('没有配置！');
    }

    public static function parseEncryptedMsgData(): array
    {
        $platform = self::getPlatform();
        if ($platform) {
            $result = $platform->parseEncryptedData(
                Request::str('msg_signature'),
                Request::str('timestamp'),
                Request::str('nonce'),
                Request::raw()
            );

            Log::debug('wxplatform', $result);

            return $result;

        }

        return err('没有配置！');
    }

    /**
     * 获取公众号网页授权URL
     */
    public static function getAuthorizationCodeRedirectUrl(
        accountModelObj $account,
        $redirect_url,
        string $scope = self::SCOPE_SNS_API_BASE,
        string $state = ''
    ): string {
        $component_appid = settings('account.wx.platform.config.appid');
        $appid = $account->settings('authdata.authorization_info.authorizer_appid');
        if (empty($component_appid) || empty($appid)) {
            return '';
        }

        return str_replace(['{APPID}', '{REDIRECT_URI}', '{SCOPE}', '{STATE}', '{COMPONENT_APPID}'], [
            $appid,
            urlencode($redirect_url),
            $scope,
            $state,
            $component_appid,
        ], self::AUTHORIZATION_CODE_URL);
    }

    public static function getUserInfo(accountModelObj $account, $code): array
    {
        $appid = $account->settings('authorization_info.authorizer_appid');
        if (empty($appid)) {
            return err('配置不正确, appid为空！');
        }
        $res = self::getAccessToken($appid, $code);
        if (is_error($res)) {
            return $res;
        }
        $scopes = explode($res['scope'], ',');
        if (in_array(self::SCOPE_SNS_API_USERINFO, $scopes)) {
            $res = self::getUserProfile($res['access_token'], $res['openid']);
            if (!is_error($res)) {
                return $res;
            }
        }

        return [
            'openid' => $res['openid'],
        ];
    }

    public static function getAccessToken($appid, $code): array
    {
        $component_appid = settings('account.wx.platform.config.appid');
        if (empty($component_appid)) {
            return err('配置不正确, component_appid为空！');
        }

        $component_access_token = self::getComponentAccessToken();
        if (empty($component_access_token)) {
            return err('无法获取component access token');
        }

        $data = HttpUtil::get(
            str_replace(['{APPID}', '{CODE}', '{COMPONENT_APPID}', '{COMPONENT_ACCESS_TOKEN}'], [
                $appid,
                $code,
                $component_appid,
                $component_access_token,
            ], self::ACCESS_TOKEN_URL)
        );

        if (empty($data)) {
            return err('接口请求失败！');
        }

        $result = json_decode($data, true);
        if (empty($result)) {
            return err('接口返回数据为空！');
        }

        if (!empty($result['errcode'])) {
            return err($result['errmsg']);
        }

        return $result;
    }

    public static function getUserProfile($access_token, $openid): array
    {
        $data = HttpUtil::get(
            str_replace(['{ACCESS_TOKEN}', '{OPENID}'], [$access_token, $openid], self::GET_USER_PROFILE)
        );

        $result = json_decode($data, true);
        if (empty($result)) {
            return err('接口返回数据为空！');
        }

        if (!empty($result['errcode'])) {
            return err($result['errmsg']);
        }

        return $result;
    }

    public static function getUserProfile2($access_token, $openid, $create_user = false)
    {
        $data = HttpUtil::get(
            str_replace(['{ACCESS_TOKEN}', '{OPENID}'], [$access_token, $openid], self::GET_USER_PROFILE2)
        );

        $result = json_decode($data, true);
        if (empty($result)) {
            return err('接口返回数据为空！');
        }

        if (!empty($result['errcode'])) {
            return err($result['errmsg']);
        }

        if ($create_user) {
            $user = User::get($result['openid'], true);
            if (empty($user)) {
                $user = User::create([
                    'app' => User::THIRD_ACCOUNT,
                    'nickname' => $result['nickname'],
                    'avatar' => $result['headimgurl'],
                    'openid' => $result['openid'],
                ]);
                if ($user) {
                    $user->set('fansData', $result);
                }
            }
        }

        return $result;
    }

    public static function getComponentTicket(): string
    {
        $data = Config::wxplatform('ticket', settings('account.wx.platform.ticket', []));
        if ($data && $data['ComponentVerifyTicket']) {
            return $data['ComponentVerifyTicket'];
        }

        return '';
    }

    public static function getComponentAccessToken(): string
    {
        $tokenData = Config::wxplatform('token', settings('account.wx.platform.token', []));
        if ($tokenData && $tokenData['component_access_token']) {
            if (time() - $tokenData['createtime'] < intval($tokenData['expires_in'])) {
                return $tokenData['component_access_token'];
            }
        }

        $ticket = self::getComponentTicket();
        if (empty($ticket)) {
            return '';
        }

        $platform = self::getPlatform();
        if ($platform) {
            $result = $platform->getComponentAccessToken($ticket);
            if ($result['errcode'] != 0) {
                Log::error('wxplatform', [
                    'fn' => 'getComponentAccessToken',
                    'error' => $result,
                ]);

                return '';
            }
            $result['createtime'] = time();
            Config::wxplatform('token', $result, true);

            return $result['component_access_token'];
        }

        return '';
    }

    public static function getPreAuthCode(): string
    {
        $accessToken = self::getComponentAccessToken();
        if (empty($accessToken)) {
            return '';
        }

        $platform = self::getPlatform();
        if ($platform) {
            $result = $platform->getPreAuthCode($accessToken);
            if ($result['errcode'] != 0) {
                Log::error('wxplatform', [
                    'fn' => 'getPreAuthCode',
                    'error' => $result,
                ]);

                return '';
            }

            return $result['pre_auth_code'];
        }

        return '';
    }

    private static function getRedirectUrl(array $params = []): string
    {
        $params = array_merge([
            'memo' => '该程序文件是微信第三方平台授权接入回调程序，程序根据需要自动生成！',
            'op' => self::AUTH_REDIRECT_OP,
        ], $params);

        $v = sha1(http_build_query($params));

        $filename = "/$v.php";
        Helper::createApiRedirectFile($filename, 'wxplatform', $params);

        $notify_url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        $notify_url .= $filename;

        return $notify_url;
    }

    /**
     * 获取授权接入的授权页面网址
     */
    public static function getPreAuthUrl(array $params = []): string
    {
        $preAuthCode = self::getPreAuthCode();
        if ($preAuthCode) {
            $platform = self::getPlatform();
            if ($platform) {
                return $platform->getAuthRedirectUrl($preAuthCode, self::getRedirectUrl($params));
            }
        }

        return '';
    }

    public static function getAuthData(string $authCode): array
    {
        $accessToken = self::getComponentAccessToken();
        if ($accessToken) {
            $platform = self::getPlatform();
            if ($platform) {
                $result = $platform->getAuthData($accessToken, $authCode);
                if (is_error($result)) {
                    return $result;
                }
                if ($result['errcode'] != 0) {
                    return error(intval($result['errcode']), strval($result['errmsg']));
                }

                return $result;
            }
        }

        return err('暂时无法请求！');
    }

    public static function refreshAuthorizerAccessToken(string $app_id, string $refreshAccessToken): array
    {
        $accessToken = self::getComponentAccessToken();
        if ($accessToken) {
            $platform = self::getPlatform();
            if ($platform) {
                $result = $platform->refreshAuthorizerAccessToken($accessToken, $app_id, $refreshAccessToken);
                if (is_error($result)) {
                    return $result;
                }
                if ($result['errcode'] != 0) {
                    return error(intval($result['errcode']), strval($result['errmsg']));
                }

                return $result;
            }
        }

        return err('暂时无法请求！');
    }

    public static function getAuthProfile(string $app_id): array
    {
        $accessToken = self::getComponentAccessToken();
        if ($accessToken) {
            $platform = self::getPlatform();
            if ($platform) {
                $result = $platform->getAuthProfile($app_id, $accessToken);
                if (is_error($result)) {
                    return $result;
                }
                if ($result['errcode'] != 0) {
                    return error(intval($result['errcode']), strval($result['errmsg']));
                }

                return $result;
            }
        }

        return err('暂时无法请求！');
    }

    public static function getAuthQRCode($token, string $scene, $action = self::TEMP_QRCODE): array
    {
        $url = str_replace('{TOKEN}', $token, self::CREATE_AUTHORIZER_QRCODE_URL);
        $params = [
            'action_name' => $action,
            'action_info' => [
                'scene' => [
                    'scene_str' => $scene,
                ],
            ],
        ];

        if ($action == self::TEMP_QRCODE) {
            //微信官方临时二维码默认30秒失效
            $params['expire_seconds'] = 3600;
        }

        $result = HttpUtil::post($url, $params);

        Log::debug('wxplatform', $result);

        if (is_error($result)) {
            return $result;
        }

        if ($result['errcode'] != 0) {
            return error(intval($result['errcode']), strval($result['errmsg']));
        }

        $result['content'] = $result['url'];
        $result['url'] = str_replace('{TICKET}', $result['ticket'], self::SHOW_QRCODE_URL);

        return $result;
    }

    /**
     * 微信授权事件通知处理
     */
    public static function handleAuthorizerNotify(): string
    {
        //平台授权通知
        $result = WxPlatform::parseEncryptedData(function ($result) {
            //授权和授权更新
            if ($result['InfoType'] === 'authorized' || $result['InfoType'] === 'updateauthorized') {

                $res = Account::createOrUpdateFromWxPlatform(
                    Request::int('agent'),
                    $result['AuthorizerAppid'],
                    $result
                );
                if (is_error($res)) {
                    return $res;
                }

                return ['message' => 'Ok'];

            } elseif ($result['InfoType'] === 'unauthorized') {
                //取消授权
                return Account::disableWxPlatformAccount($result['AuthorizerAppid']);

            } elseif ($result['InfoType'] === 'component_verify_ticket') {

                //component_verify_ticket推送                
                if (Config::wxplatform('ticket', $result, true)) {
                    return ['message' => 'Ok'];
                }
            }

            return [];
        });

        if (is_error($result)) {
            Log::error('wxplatform', [
                'fn' => 'handleAuthorizerNotify',
                'error' => $result,
            ]);
        }

        return self:: SUCCESS_RESPONSE;
    }

    /**
     * 微信消息处理
     *
     */
    public static function handleAuthorizerEvent(): string
    {
        $msg = WxPlatform::parseEncryptedMsgData();
        if (is_error($msg)) {
            Log::error('wxplatform', [
                'fn' => 'handleAuthorizerEvent[1]',
                'error' => $msg,
            ]);
        } else {
            $result = [];

            if ($msg['Event'] == 'subscribe') {
                $result = self::subscribe($msg);
            } elseif ($msg['Event'] == 'SCAN') {
                $result = self::scan($msg);
            } elseif ($msg['Event'] == 'unsubscribe') {
                self::unsubscribe($msg);
            }

            if (is_error($result)) {
                Log::error('wxplatform', [
                    'fn' => 'handleAuthorizerEvent[2]',
                    'error' => $result,
                ]);

                if (DEBUG) {
                    $result = self::createToUserTextMsg(
                        $msg['ToUserName'],
                        $msg['FromUserName'],
                        '发生错误：'.$result['message']
                    );
                }
            }

            if ($result && is_string($result)) {
                return self::getEncryptedMsg($result);
            }
        }

        return self::SUCCESS_RESPONSE;
    }

    public static function createToUserTextMsg(
        string $from_user,
        string $to_user,
        string $text,
        int $timestamp = 0
    ): string {
        return We7::array2xml([
            'ToUserName' => $to_user,
            'FromUserName' => $from_user,
            'CreateTime' => empty($timestamp) ? time() : $timestamp,
            'MsgType' => 'text',
            'Content' => $text,
        ]);
    }

    /**
     * 创建一个推送给指定用户的图文消息
     * @param array $params 参数：['title'] 标题，['desc'] 描述，['image']图片，['url']链接
     */
    public static function createToUserNewsMsg(
        string $from_user,
        string $to_user,
        array $params = [],
        int $timestamp = 0
    ): string {
        return We7::array2xml([
            'ToUserName' => $to_user,
            'FromUserName' => $from_user,
            'CreateTime' => empty($timestamp) ? time() : $timestamp,
            'MsgType' => 'news',
            'ArticleCount' => 1,
            'Articles' => [
                'item' => [
                    'Title' => $params['title'],
                    'Description' => $params['desc'],
                    'PicUrl' => $params['image'],
                    'Url' => $params['url'],
                ],
            ],
        ]);
    }

    public static function getEncryptedMsg(string $msg): string
    {
        $platform = self::getPlatform();
        if ($platform) {
            return $platform->encryptMsg($msg);
        }

        return '';
    }

    public static function subscribe(array $msg)
    {
        return self::open($msg);
    }

    public static function scan(array $msg)
    {
        return self::open($msg);
    }

    public static function unsubscribe(array $msg)
    {
        unset($msg);

        return err('unsubscribe unimplemented!');
    }

    public static function verifyData($params = []): array
    {
        unset($params);

        if (!App::isWxPlatformEnabled()) {
            return err('没有启用！');
        }

        return [];
    }

    public static function createOrder(deviceModelObj $device, userModelObj $user, accountModelObj $account): bool
    {
        //获取第一货道上的商品，如果该商品数量不足，则去获取其它货道上的相同商品
        $goods = $device->getGoodsByLane(0);
        if ($goods && $goods['num'] < 1) {
            $goods = $device->getGoods($goods['id']);
        }

        if (empty($goods) || $goods['num'] < 1) {
            ZovyeException::throwWith('找不到指定商品或商品库存不足！', -1, $device);
        }

        $uid = empty($msg['Ticket']) ? sha1(time()) : sha1($msg['Ticket']);
        $order_uid = Order::makeUID($user, $device, $uid);

       return Job::createThirdPartyPlatformOrder([
            'device' => $device->getId(),
            'user' => $user->getId(),
            'account' => $account->getId(),
            'orderUID' => $order_uid,
            'extra' => [],
        ]);
    }

    public static function open($msg)
    {
        try {
            $res = self::verifyData($msg);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：'.$res['message']);
            }

            $event_key = str_replace('qrscene_', '', strval($msg['EventKey']));

            list($prefix, $first, $second) = explode(':', $event_key, 3);

            if ($prefix != App::uid(6)) {

                Log::error('wxplatform', [
                    'msg' => $msg,
                    'error' => '未知来源用户，已忽略！',
                ]);

                return [];
            }

            $account_name = $msg['ToUserName'];
            $account = Account::findOneFromName($account_name);
            if (empty($account)) {
                throw new RuntimeException('找不到指定的公众号：'.$account_name);
            }

            $device = Device::get($second);
            if (empty($device)) {
                throw new RuntimeException('找不到这个设备：'.$second);
            }

            $user = User::getOrCreate($msg['FromUserName'], User::THIRD_ACCOUNT);
            if (empty($user)) {
                throw new RuntimeException('找不到这个用户[1]！');
            }

            if ($user->isBanned()) {
                throw new RuntimeException('用户已被禁用[1]！');
            }

            //如果是来自屏幕二维码
            if ($first == 'device') {
                $res = Helper::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);

                if (!is_error($res)) {
                    if (!self::createOrder($device, $user, $account)) {
                        throw new RuntimeException('启动订单任务失败[1]！');
                    }
                    return true;
                }

                Log::debug('wxplatform', [
                    'user' => $user->profile(),
                    'device' => $device->profile(),
                    'account' => $account->profile(),
                    'result' => $res,
                ]);

                $message = $account->getOpenMsg($msg['ToUserName'], $msg['FromUserName'], $device->getUrl());
                if (empty($message)) {
                    ZovyeException::throwWith($res['message'], -1, $device);
                }

                //推送消息
                return $message;
            }

            $user = User::get($first);
            if (empty($user)) {
                throw new RuntimeException('找不到这个用户[2]！');
            }

            if ($user->isBanned()) {
                throw new RuntimeException('用户已被禁用[2]！');
            }

            //出货时机是用户点击链连后，直接返回推送的消息
            if (!empty($account->settings('config.open.timing'))) {
                $user->setLastActiveDevice($device);

                return $account->getOpenMsg($msg['ToUserName'], $msg['FromUserName'], $account->getUrl());
            }

            $res = Helper::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);

            if (is_error($res)) {
                ZovyeException::throwWith($res['message'], -1, $device);
            }

            //创建订单
            if (!self::createOrder($device, $user, $account)) {
                throw new RuntimeException('启动订单任务失败[2]！');
            }

            //推送设备首页链接
            return $account->getOpenMsg($msg['ToUserName'], $msg['FromUserName'], $device->getUrl());

        } catch (ZovyeException $e) {
            Log::error('wxplatform', [
                'msg' => $msg,
                'error' => $e->getMessage(),
            ]);

            $device = $e->getDevice();
            if ($device) {
                $device->appShowMessage($e->getMessage(), 'error');
            }

            return self::createToUserTextMsg(
                $msg['ToUserName'],
                $msg['FromUserName'],
                $e->getMessage()
            );

        } catch (Exception $e) {

            Log::error('wxplatform', [
                'msg' => $msg,
                'error' => $e->getMessage(),
            ]);

            return err($e->getMessage());
        }
    }
}
