<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

class DouYin
{
    const API_URL = 'https://open.douyin.com';

    private $client_key;
    private $client_secret;

    public function __construct($key, $secret)
    {
        $this->client_key = $key;
        $this->client_secret = $secret;
    }

    public function getAuthorizeRedirectUrl($url, array $scopes = []): string
    {
        $path = '/platform/oauth/connect/?';
        $data = [
            'client_key' => $this->client_key,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'optionalScope' => '',
            'redirect_uri' => $url,
        ];

        return self::API_URL.$path.http_build_query($data);
    }

    public function getSilenceAuthorizeRedirectUrl($url): string
    {
        $path = '/oauth/authorize/v2/?';
        $data = [
            'client_key' => $this->client_key,
            'response_type' => 'code',
            'scope' => 'login_id',
            'optionalScope' => '',
            'redirect_uri' => $url,
        ];

        return self::API_URL.$path.http_build_query($data);
    }

    public function getAccessToken($code)
    {
        $path = '/oauth/access_token/';

        $res = Util::post(self::API_URL.$path, [
            'client_key' => $this->client_key,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',

        ], false);

        if (is_error($res)) {
            return $res;
        }

        if (getArray($res, 'data.error_code', 1) > 0) {
            return err(getArray($res, 'data.description', 'unknown error'));
        }

        $data = getArray($res, 'data', []);
        if (empty($data)) {
            return err('返回数据为空！');
        }

        return $data;
    }

    public function refreshAccessToken($refresh_token)
    {
        $path = '/oauth/renew_refresh_token/';

        $res = Util::post(self::API_URL.$path, [
            'client_key' => $this->client_key,
            'refresh_token' => $refresh_token,
        ], false);

        if (is_error($res)) {
            return $res;
        }

        if (getArray($res, 'data.error_code', 1) > 0) {
            return err(getArray($res, 'data.description', 'unknown error'));
        }

        $data = getArray($res, 'data', []);
        if (empty($data)) {
            return err('返回数据为空！');
        }

        return $data;
    }

    public function getUserInfo($access_token, $openid)
    {
        $url = self::API_URL.'/oauth/userinfo/?'.http_build_query([
                'open_id' => $openid,
            ]);

        $res = Util::get($url, 3, [
            CURLOPT_HTTPHEADER => [
                'access-token' => $access_token,
            ],
        ], true);
        if (is_error($res)) {
            return $res;
        }

        if (getArray($res, 'data.error_code', 1) > 0) {
            return err(getArray($res, 'data.description', 'unknown error'));
        }

        $data = getArray($res, 'data', []);
        if (empty($data)) {
            return err('返回数据为空！');
        }

        return $data;
    }

    public function fansCheck($access_token, $openid, $follower_openid)
    {
        $url = self::API_URL.'/fans/check/?'.http_build_query([
                'open_id' => $openid,
                'follower_open_id' => $follower_openid,
            ]);

        $res = Util::get($url, 3, [
            CURLOPT_HTTPHEADER => [
                'access-token' => $access_token,
            ],
        ], true);

        if (is_error($res)) {
            return $res;
        }

        if (getArray($res, 'extra.error_code', 1) > 0) {
            return err(getArray($res, 'extra.description', 'unknown error'));
        }

        return getArray($res, 'data.is_follower', false);
    }

    public function getFollowingList($access_token, $openid, $cursor = 0, $count = 10)
    {
        $url = self::API_URL.'/following/list/?'.http_build_query([
                'open_id' => $openid,
                'cursor' => $cursor,
                'count' => $count,
            ]);

        $res = Util::get($url, 3, [
            CURLOPT_HTTPHEADER => [
                'access-token' => $access_token,
            ],
        ], true);
        if (is_error($res)) {
            return $res;
        }

        if (getArray($res, 'extra.error_code', 1) > 0) {
            return err(getArray($res, 'extra.description', 'unknown error'));
        }

        $data = getArray($res, 'data', []);
        if (empty($data)) {
            return err('返回数据为空！');
        }

        return $data;
    }

    public static function getInstance(): DouYin
    {
        static $instance = null;
        if (is_null($instance)) {
            $config = Config::douyin('client', []);
            $instance = new static($config['key'], $config['secret']);
        }

        return $instance;
    }

    public static function redirectToAuthorizeUrl($url, $fetch_url = false)
    {
        $o = self::getInstance();
        $res = $o->getAuthorizeRedirectUrl($url, ['user_info', 'following.list']);
        if ($fetch_url) {
            return $res;
        }
        Util::redirect($res);
        exit();
    }

    public static function isTokenExpired(userModelObj $user): bool
    {
        $token = $user->get('douyin_token', []);

        return empty($token) || time() - $token['updatetime'] > $token['expires_in'] - 1000;
    }

    public static function isFans(userModelObj $user, $openid)
    {
        $access_token = $user->settings('douyin_token.access_token', '');
        $o = self::getInstance();
        return $o->fansCheck($access_token, $openid, $user->getOpenid());
    }

    public static function getUserFollowList(userModelObj $user, $cursor = 0, $count = 10)
    {
        $access_token = $user->settings('douyin_token.access_token', '');
        $o = self::getInstance();
        return $o->getFollowingList($access_token, $user->getOpenid(), $cursor, $count);
    }

    public static function makeHomePageUrl($url)
    {
        $result = [];
        if (preg_match('/https:\/\/.*\/video\/(\d*)/', $url, $result)) {
            return "snssdk1128://video/profile/$result[1]";
        }
        if (preg_match('/author_id=(\d*)/', $url, $result)) {
            return "snssdk1128://user/profile/$result[1]";
        }
        if (is_numeric($url)) {
            return "snssdk1128://user/profile/$url";
        }

        return $url;
    }
}