<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\Contract\IHttpClient;

class ChargingServ
{
    /**
     * @var $http_client IHttpClient
     */
    static $http_client = null;

    static $app = [];

    private function __construct()
    {
    }

    public static function setHttpClient(IHttpClient $http_client)
    {
        self::$http_client = $http_client;
    }

    public static function query(
        $path,
        array $params = [],
        $body = '',
        string $contentType = '',
        string $method = ''
    ) {
        if (!self::$http_client) {
            return error(State::ERROR, '没有配置请求对象！');
        }

        $url = Config::charging('server.url');
        $access_token = Config::charging('server.access_token');

        if (empty($url) || empty($access_token)) {
            return error(State::ERROR, '配置错误！');
        }

        if ($url[strlen($url) - 1] != '/') {
            $url .= '/';
        }

        $headers = [
            'access_token' => $access_token,
        ];

        if ($body) {
            if (empty($method)) {
                $method = 'POST';
            }

            if (is_string($body)) {
                if (empty($contentType)) {
                    $contentType = 'application/x-www-form-urlencoded';

                }
                if ($contentType == 'application/x-www-form-urlencoded') {
                    parse_str($body, $result);
                    $params = array_merge($params, $result);
                }
            } else {
                $contentType = 'application/json';
                $body = json_encode($body);
            }

            if ($contentType) {
                $headers['Content-Type'] = $contentType;
            }
        } else {
            if (empty($method)) {
                $method = 'GET';
            }
        }

        $url .= '?'.http_build_query($params);
        return self::$http_client->request($url, $method, $headers, $body);
    }

    public static function GetVersion(): array {
        $res = self::query('/');
        if (is_error($res)) {
            return $res;
        }
        if (!$res['status']) {
            return err($res['data']['message'] ?? '请求失败！');
        }
        return [
            'version' => $res['data']['version'] ?? 'n/a',
            'build' => $res['data']['build'] ?? '',
        ];
    }

    public static function createOrUpdateGroup($group)
    {

    }
}