<?php


namespace zovye;


use Exception;
use RuntimeException;
use wx\Platform;

class WxPlatform
{
    const AUTH_NOTIFY = 'notify';
    const AUTHORIZER_EVENT = 'event';
    const AUTH_REDIRECT_OP = 'authcode';

    const SUCCESS_RESPONSE = 'success';

    const CREATE_AUTHORIZER_QRCODE_URL = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={TOKEN}';
    const SHOW_QRCODE_URL = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={TICKET}';

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
     * @return array
     */
    public static function parseEncryptedData(callable $fn = null): array
    {
        $platform = self::getPlatform();
        if ($platform) {
            $result = $platform->parseEncryptedData(
                request::str('msg_signature'),
                request::str('timestamp'),
                request::str('nonce'),
                request::raw()
            );

            //Util::logToFile('wxplatform', $result);

            if (is_error($result)) {
                return $result;
            }

            if ($result['InfoType'] === 'component_verify_ticket') {
                if (updateSettings('account.wx.platform.ticket', $result)) {
                    return ['message' => '成功'];
                }
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
                request::str('msg_signature'),
                request::str('timestamp'),
                request::str('nonce'),
                request::raw()
            );

            //Util::logToFile('wxplatform', $result);

            if (is_error($result)) {
                return $result;
            }

            return $result;
        }

        return err('没有配置！');
    }

    public static function getComponentTicket(): string
    {
        $data = settings('account.wx.platform.ticket', []);
        if ($data && $data['ComponentVerifyTicket']) {
            return $data['ComponentVerifyTicket'];
        }

        return '';
    }

    public static function getComponentAccessToken(): string
    {
        $tokenData = settings('account.wx.platform.token', []);
        if ($tokenData && $tokenData['component_access_token']) {
            if (time() - $tokenData['createtime'] < intval($tokenData['expires_in']) - 600) {
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
            if ($result['errcode']) {
                Util::logToFile('wxplatform', [
                    'fn' => 'getComponentAccessToken',
                    'error' => $result,
                ]);
                return '';
            }
            $result['createtime'] = time();
            updateSettings('account.wx.platform.token', $result);
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
            if ($result['errcode']) {
                Util::logToFile('wxplatform', [
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

        $filename = "/{$v}.php";
        Util::createApiRedirectFile($filename, 'wxplatform', $params);

        $notify_url = _W('siteroot');
        $path = 'addons/' . APP_NAME . '/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        $notify_url .= $filename;
        return $notify_url;
    }

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
                return $platform->getAuthData($accessToken, $authCode);
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
                return $platform->refreshAuthorizerAccessToken($accessToken, $app_id, $refreshAccessToken);
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
                return $platform->getAuthProfile($app_id, $accessToken);
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
                ]
            ]
        ];

        if ($action == self::TEMP_QRCODE) {
            //微信官方临时二维码默认30秒失效
            $params['expire_seconds'] = 3600;
        }

        $result = Util::post($url, $params);

        //Util::logToFile('wxplatform', $result);

        if ($result && $result['errcode'] > 0) {
            return err($result['errmsg']);
        }

        $result['content'] = $result['url'];
        $result['url'] = str_replace('{TICKET}', $result['ticket'], self::SHOW_QRCODE_URL);

        return $result;
    }

    public static function handleAuthorizerNotify(): string
    {
        //平台授权通知
        $result = WxPlatform::parseEncryptedData(function ($result) {
            if ($result['InfoType'] === 'authorized' || $result['InfoType'] === 'updateauthorized') {
                $res = Account::createOrUpdateFromWxPlatform(request::int('agent'), $result['AuthorizerAppid'], $result);
                if (is_error($res)) {
                    return $res;
                }
                return ['message' => 'Ok'];
            } elseif ($result['InfoType'] === 'unauthorized') {
                return Account::disableWxPlatformAccount($result['AuthorizerAppid']);
            }
            return [];
        });

        if (is_error($result)) {
            Util::logToFile('wxplatform', [
                'fn' => 'handleAuthorizerNotify',
                'error' => $result,
            ]);
        }

        return self:: SUCCESS_RESPONSE;
    }

    public static function handleAuthorizerEvent(): string
    {
        $msg = WxPlatform::parseEncryptedMsgData();
        if (is_error($msg)) {
            Util::logToFile('wxplatform', [
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
                Util::logToFile('wxplatform', [
                    'fn' => 'handleAuthorizerEvent[2]',
                    'error' => $result,
                ]);
            }

            $message = is_error($result) ? "发生错误：{$result['message']}" : strval($result['message']);
            if (!empty($message)) {
                $xml_msg = self::getToUserMsg($msg['ToUserName'], $msg['FromUserName'], $message, time());
                return self::getEncryptedMsg($xml_msg);
            }
        }

        return self::SUCCESS_RESPONSE;
    }

    public static function getToUserMsg(string $from_user_name, string $to_user_name, string $msg, int $ts): string
    {
        return We7::array2xml([
            'ToUserName' => $to_user_name,
            'FromUserName' => $from_user_name,
            'CreateTime' => $ts,
            'MsgType' => 'text',
            'Content' => $msg,
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

    public static function subscribe(array $msg): array
    {
        return self::open($msg);
    }

    public static function scan(array $msg): array
    {
        return self::open($msg);
    }

    public static function unsubscribe(array $msg): array
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

    public static function open($msg): array
    {
        try {
            $res = self::verifyData($msg);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：' . $res['message']);
            }

            $account_name = $msg['ToUserName'];
            $acc = Account::findOne(['name' => $account_name]);
            if (empty($acc)) {
                throw new RuntimeException('找不到指定的公众号：' . $account_name);
            }

            $config = $acc->settings('config.open', []);

            $makePushMsgFN = function($url) use ($config){
                if ($config['msg']) {
                    $str = strval($config['msg']);
                    if (stripos($str, '{url}') !== false && stripos($str, '{/url}') !== false) {
                        $arr = explode('{url}', $str, 2);
                        $text = $arr[0];
                        $arr = explode('{/url}', $arr[1], 2);
                        $text .= '<a href="' . $url . '">' . $arr[0] . '</a>' . $arr[1];
                    } else {
                        $text = str_replace('{url}', "<a href=\"{$url}\">这里</a>", $str);
                    }
                    return $text;
                }
                return '';
            };

            //出货时机是用户点击链连后，直接指定回推送的消息
            if (!empty($config['timing'])) {
                return ['message' => $makePushMsgFN($acc->getUrl())];
            }

            list($prefix, $first, $second) = explode(':', ltrim(strval($msg['EventKey']), 'qrscene_'), 3);
            if ($prefix != App::uid(6)) {
                return [];
            }

            $device = Device::get($second);

            if (empty($device)) {
                throw new RuntimeException('找不到这个设备！');
            }

            if ($first == 'device') {
                return [
                    'message' => $makePushMsgFN($device->getUrl()),
                ];
            }

            $user = User::get($first);
            if (empty($user)) {
                throw new RuntimeException('找不到这个用户！');
            }

            if ($user->isBanned()) {
                throw new RuntimeException('用户已被禁用！');
            }

            $goods = $device->getGoodsByLane(0);
            if (empty($goods)) {
                ZovyeException::throwWith('道货（1）没有商品，请联系管理员！', -1, $device);
            }

            if ($goods['num'] < 1) {
                ZovyeException::throwWith('当前商品库存不足！', -1, $device);
              }

            if (DEBUG) {
                Util::logToFile('wxplatform', [
                    'account' => $acc->format(),
                    'user' => $user->profile(),
                    'device' => $device->profile(),
                ]);
            }

            $uid = sha1($msg['Ticket']);
            $order_uid = substr("U{$user->getId()}D{$device->getId()}{$uid}", 0, MAX_ORDER_NO_LEN);

            Job::createSpecialAccountOrder([
                'device' => $device->getId(),
                'user' => $user->getId(),
                'account' => $acc->getId(),
                'orderUID' => $order_uid,
                'extra' => [],
            ]);

            return ['message' => $makePushMsgFN($acc->getUrl())];

        } catch (ZovyeException $e) {
            $device = $e->getDevice();
            if ($device) {
                $device->appShowMessage($e->getMessage(), 'error');
            }

            return err($e->getMessage());

        } catch (Exception $e) {
            return err($e->getMessage());
        }
    }
}
