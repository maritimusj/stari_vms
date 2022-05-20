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
    public static function getWx(): WeiXinAccount
    {
        static $wx = null;
        if (empty($wx)) {
            We7::load()->classs('weixin.account');
            $wx = WeAccount::create(We7::uniacid());
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
    public static function sendTplNotice($openid, $tpl_id, $content, string $url = ''): bool
    {
        $wx = self::getWx();
        $result = $wx->sendTplNotice($openid, $tpl_id, $content, $url);

        return !is_error($result);
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
}
