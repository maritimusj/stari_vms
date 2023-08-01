<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use WeAccount;
use WeiXinAccount;
use WxappAccount;
use function cache_delete;
use function cache_system_key;

class Wx
{
    const CREATE_QRCODE_URL = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=';
    const SHOW_QRCODE_URL = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=';
    const ADD_TEMPLATE_URL = 'https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token=';
    const DELETE_TEMPLATE_URL = 'https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token=';
    const GET_ALL_TEMPLATE_URL = 'https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token=';
    const SEND_TEMPLATE_MSG_URL = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=';

    public static function deleteAccessTokenCache()
    {
        $key = cache_system_key('accesstoken', array('uniacid' => We7::uniacid()));
        cache_delete($key);
    }

    public static function trim($str, $max_len)
    {
        if (mb_strlen($str) > $max_len) {
            return mb_substr($str, 0, $max_len - 1) . '…';
        }
        return $str;
    }

    public static function trim_thing($str)
    {
        return self::trim($str, 20);
    }

    public static function trim_character($str)
    {
        return self::trim($str, 32);
    }

    public static function checkResult($result)
    {
        if ($result['errcode'] === 40001) {
            self::deleteAccessTokenCache();
        }

        if ($result['errcode'] != 0) {
            return err($result['errmsg'] ?? '发生错误！');
        }

        return $result;
    }

    public static function getWxAccount(): WeiXinAccount
    {
        static $wx_account = null;

        if (empty($wx_account)) {
            if ($GLOBALS['_W']['account'] instanceof WeiXinAccount) {
                $wx_account = $GLOBALS['_W']['account'];
            } else {
                We7::load()->classs('weixin.account');
                $wx_account = WeAccount::create(We7::uniacid());
            }
        }

        return $wx_account;
    }

    public static function getAllTemplate()
    {
        $token = self::getWxAccount()->getAccessToken();
        if (is_error($token)) {
            return $token;
        }

        $api_url = self::GET_ALL_TEMPLATE_URL.$token;

        return self::checkResult(
            HttpUtil::get($api_url, 3, [], true)
        );
    }

    public static function addTemplate($template_id, $keyword_name_list = [])
    {
        $token = self::getWxAccount()->getAccessToken();
        if (is_error($token)) {
            return $token;
        }

        $api_url = self::ADD_TEMPLATE_URL.$token;

        return self::checkResult(
            HttpUtil::post($api_url, [
                'template_id_short' => $template_id,
                'keyword_name_list' => $keyword_name_list,
            ])
        );
    }

    public static function deleteTemplate($template_id)
    {
        $token = self::getWxAccount()->getAccessToken();
        if (is_error($token)) {
            return $token;
        }

        $api_url = self::DELETE_TEMPLATE_URL.$token;

        return self::checkResult(
            HttpUtil::post($api_url, [
                'template_id' => $template_id,
            ])
        );
    }

    public static function sendTemplateMsg($data)
    {
        $token = self::getWxAccount()->getAccessToken();
        if (is_error($token)) {
            return $token;
        }

        $api_url = self::SEND_TEMPLATE_MSG_URL.$token;

        return self::checkResult(HttpUtil::post($api_url, $data));
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
        return self::checkResult(self::getWxAccount()->sendTplNotice($openid, $tpl_id, $content, $url));
    }

    /**
     * 微信客服消息
     * @param $msg
     * @return mixed
     */
    public static function sendCustomNotice($msg): bool
    {
        $result = self::getWxAccount()->sendCustomNotice($msg);

        return !is_error($result);
    }

    public static function getWxApp($config = []): WxappAccount
    {
        static $wx_app = null;

        if (empty($wx_app)) {
            We7::load()->classs('wxapp.account');
            $wx_app = new WxappAccount($config);
        }

        return $wx_app;
    }

    public static function decodeWxAppData($code, $iv, $encryptedData, $config = [])
    {
        $wx_app = self::getWxApp($config);

        $auth_data = $wx_app->getOauthInfo($code);
        if (is_error($auth_data)) {
            return $auth_data;
        }

        if ($iv && $encryptedData) {
            //微擎的pkcs7Encode()解密函数需要从$_SESSION中读取session_key
            $_SESSION['session_key'] = $auth_data['session_key'];
            $res = $wx_app->pkcs7Encode($encryptedData, $iv);
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

    private static function getQRCodeTicket($action = '', $scene = '', $expire_seconds = 60): array
    {
        $data = [
            'expire_seconds' => $expire_seconds,
            'action_name' => $action,
            'action_info' => [
                'scene' => [],
            ],
        ];

        if (is_int($scene)) {
            $data['action_info']['scene']['scene_id'] = intval($scene);
        } else {
            $data['action_info']['scene']['scene_str'] = strval($scene);
        }

        return HttpUtil::post(self::CREATE_QRCODE_URL.self::getWxAccount()->getAccessToken(), $data);
    }

    public static function getTempQRCodeTicket($scene = '', $expire_seconds = 60): array
    {
        return self::checkResult(self::getQRCodeTicket('QR_SCENE', $scene, $expire_seconds));
    }

    public static function getLimitQRCodeTicket($scene = '', $expire_seconds = 60): array
    {
        return self::checkResult(self::getQRCodeTicket('QR_LIMIT_STR_SCENE', $scene, $expire_seconds));
    }

    public static function getTempQRCodeUrl($scene = '', $expire_seconds = 60): string
    {
        $res = self::getTempQRCodeTicket($scene, $expire_seconds);
        if ($res && $res['ticket']) {
            return self::SHOW_QRCODE_URL.$res['ticket'];
        }

        return '';
    }
}
