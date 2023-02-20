<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use WeAccount;
use WeiXinAccount;
use WxappAccount;

class Wx
{
    const CREATE_QRCODE_URL = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={access_token}';

    public static function getWx(): WeiXinAccount
    {
        static $wx = null;
        if (empty($wx)) {
            if ($GLOBALS['_W']['account'] instanceof WeiXinAccount) {
                $wx = $GLOBALS['_W']['account'];
            } else {
                We7::load()->classs('weixin.account');
                $wx = WeAccount::create(We7::uniacid());
            }
        }

        return $wx;
    }

    /**
     * 微信模板消息通知
     * @param $openid
     * @param $tpl_id
     * @param $content
     * @param string $url
     * @return mixed
     */
    public static function sendTplNotice($openid, $tpl_id, $content, string $url = '')
    {
        $wx = self::getWx();
        return $wx->sendTplNotice($openid, $tpl_id, $content, $url);
    }

    /**
     * 微信客服消息
     * @param $msg
     * @return mixed
     */
    public static function sendCustomNotice($msg): bool
    {
        $wx = self::getWx();
        $result = $wx->sendCustomNotice($msg);

        return !is_error($result);
    }

    public static function getWxApp($config = []): WxappAccount
    {
        static $wxApp = null;
        if (empty($wxApp)) {
            We7::load()->classs('wxapp.account');
            $wxApp = new WxappAccount($config);
        }

        return $wxApp;
    }

    public static function decodeWxAppData($code, $iv, $encryptedData, $config = [])
    {
        $wxApp = self::getWxApp($config);
        $auth_data = $wxApp->getOauthInfo($code);
        if (is_error($auth_data)) {
            return $auth_data;
        }

        if ($iv && $encryptedData) {
            //微擎的pkcs7Encode()解密函数需要从$_SESSION中读取session_key
            $_SESSION['session_key'] = $auth_data['session_key'];
            $res = $wxApp->pkcs7Encode($encryptedData, $iv);
            if (is_error($res)) {
                return $res;
            }
        } else {
            $res = [];
        }

        $res['session_key'] = $_SESSION['session_key'];

        if (!empty($auth_data['openid'])) {
            $res['openId'] = $auth_data['openid'];
        }

        return $res;
    }

    public static function getTempQRCodeTicket($scene = '', $expire_seconds = 60): array
    {
        $wx = self::getWx();

        $data = [
            'expire_seconds' => $expire_seconds,
            'action_name' => 'QR_SCENE',
            'action_info' => [
                'scene' => []
            ]
        ];

        if (is_int($scene)) {
            $data['action_info']['scene']['scene_id'] = intval($scene);
        } else {
            $data['action_info']['scene']['scene_str'] = strval($scene);
        }

        $token = $wx::token();

        return Util::post(str_replace('{access_token}', $token, self::CREATE_QRCODE_URL), $data);
    }
}
