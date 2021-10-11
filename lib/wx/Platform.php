<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */


namespace wx;

use zovye\Util;
use zovye\We7;
use function zovye\err;

class Platform
{
    const START_PUSH_TICKET_URL = 'https://api.weixin.qq.com/cgi-bin/component/api_start_push_ticket?component_access_token={COMPONENT_ACCESS_TOKEN}';
    const GET_COMPONENT_ACCESS_URL = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
    const GET_PRE_AUTH_CODE_URL = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token={COMPONENT_ACCESS_TOKEN}';
    const GET_ACCESS_TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token={COMPONENT_ACCESS_TOKEN}';
    const AUTH_REDIRECT_URL = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid={appid}&pre_auth_code={code}&redirect_uri={url}&auth_type={type}';
    const GET_AUTH_DATA_URL = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token={COMPONENT_ACCESS_TOKEN}';
    const GET_AUTH_PROFILE_URL = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token={COMPONENT_ACCESS_TOKEN}';

    private $app_id;
    private $secret;
    private $token;
    private $key;

    /**
     * Platform constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->app_id = strval($config['appid']);
        $this->secret = strval($config['secret']);
        $this->token = strval($config['token']);
        $this->key = strval($config['key']);
    }

    public function encryptMsg(string $data): string
    {
        list($err, $result) = (new WXBizMsgCrypt($this->app_id, $this->token, $this->key))->encryptMsg($data, time(), Util::random(16));
        if (empty($err)) {
            return $result;
        }

        if (DEBUG) {
            Util::logToFile('wxplatform', [
                'fn' => 'parseEncryptedData',
                'error' => $err,
            ]);
        }

        return '';
    }

    public function parseEncryptedData(string $msg_signature, string $timestamp, string $nonce, string $data): array
    {
        list($err, $result) = (new WXBizMsgCrypt($this->app_id, $this->token, $this->key))->decryptMsg(
            $msg_signature,
            $timestamp,
            $nonce,
            $data
        );

        if (empty($err)) {
            return We7::xml2array($result);
        }

        return err('解析错误：' . $err);
    }

    public function startPushTicket(string $token): array
    {
        $url = str_replace('{COMPONENT_ACCESS_TOKEN}', $token, self::START_PUSH_TICKET_URL);
        return Util::post($url, []);
    }

    public function getComponentAccessToken(string $ticket): array
    {
        return Util::post(self::GET_COMPONENT_ACCESS_URL, [
            'component_appid' => $this->app_id,
            'component_appsecret' => $this->secret,
            'component_verify_ticket' => $ticket,
        ]);
    }

    public function getPreAuthCode($token): array
    {
        $url = str_replace('{COMPONENT_ACCESS_TOKEN}', $token, self::GET_PRE_AUTH_CODE_URL);
        return Util::post($url, [
            'component_appid' => $this->app_id,
        ]);
    }

    public function getAuthRedirectUrl($preAuthCode, $redirectUrl, $type = 1): string
    {
        return str_replace(['{appid}', '{code}', '{url}', '{type}'], [
            $this->app_id,
            $preAuthCode,
            $redirectUrl,
            $type,
        ], self::AUTH_REDIRECT_URL);
    }

    public function getAuthData(string $accessToken, string $authCode): array
    {
        $url = str_replace('{COMPONENT_ACCESS_TOKEN}', $accessToken, self::GET_AUTH_DATA_URL);
        return Util::post($url, [
            'component_appid' => $this->app_id,
            'authorization_code' => $authCode,
        ]);
    }

    public function refreshAuthorizerAccessToken(string $componentAccessToken, string $authorizerAppId, string $resfreshToken): array
    {
        $url = str_replace('{COMPONENT_ACCESS_TOKEN}', $componentAccessToken, self::GET_ACCESS_TOKEN_URL);

        return Util::post($url, [
            'component_appid' => $this->app_id,
            'authorizer_appid' => $authorizerAppId,
            'authorizer_refresh_token' => $resfreshToken,
        ]);
    }

    public function getAuthProfile(string $appid, string $accessToken): array
    {
        $url = str_replace('{COMPONENT_ACCESS_TOKEN}', $accessToken, self::GET_AUTH_PROFILE_URL);
        return Util::post($url, [
            'component_appid' => $this->app_id,
            'authorizer_appid' => $appid,
        ]);
    }
}
