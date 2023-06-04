<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\Contract\IHttpClient;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;

class ChargingServ
{
    /**
     * @var $http_client IHttpClient
     */
    static $http_client = null;

    static $config = [];

    private function __construct()
    {
    }

    public static function setHttpClient(IHttpClient $http_client, array $config)
    {
        self::$http_client = $http_client;
        self::$config = $config;
    }

    public static function query(
        $path = '',
        array $params = [],
        $body = '',
        string $contentType = '',
        string $method = ''
    ) {
        if (!self::$http_client) {
            return err('没有配置请求对象！');
        }

        $url = self::$config['url'];
        $access_token = self::$config['access_token'];

        if (empty($url) || empty($access_token)) {
            return err('配置错误！');
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

        if ($url[strlen($url) - 1] != '/') {
            $url .= '/';
        }

        $url .= "$path?".http_build_query($params);

        return self::$http_client->request($url, $method, $headers, $body);
    }

    public static function GetVersion(): array
    {
        $res = self::query();
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

    public static function createOrUpdateGroup(device_groupsModelObj $group)
    {
        $loc = $group->getLoc();
        $res = self::query("group/{$group->getName()}", [], [
            'group' => [
                'name' => $group->getName(),
                'title' => $group->getTitle(),
                'address' => $group->getAddress(),
                'lat' => $loc['lat'],
                'lng' => $loc['lng'],
                'description' => $group->getDescription(),
            ],
            'plan' => $group->getFee(),
        ]);

        if (is_error($res)) {
            return $res;
        }

        if (!$res['status']) {
            return err($res['data']['message'] ?? '请求失败！');
        }

        return $res['data']['version'] ?? '0';
    }

    public static function removeGroup($name)
    {
        $res = self::query("group/$name", [], '', '', 'DELETE');
        if (is_error($res)) {
            return $res;
        }
        if (!$res['status']) {
            return err($res['data']['message'] ?? '请求失败！');
        }

        return true;
    }

    public static function getGroupVersion($name)
    {
        $res = self::query("group/$name/version");
        if (is_error($res)) {
            return $res;
        }
        if (!$res['status']) {
            return err($res['data']['message'] ?? '请求失败！');
        }

        return $res['data']['version'] ?? 'n/a';
    }

    public static function setDeviceGroup(deviceModelObj $device)
    {
        $group = $device->getGroup();
        if ($group) {
            $res = self::query("device/{$device->getImei()}", [], [
                'title' => $device->getName(),
                'group' => $group->getName(),
            ], '', 'PUT');
            if (is_error($res)) {
                return $res;
            }
            if (!$res['status']) {
                return err($res['data']['message'] ?? '请求失败！');
            }

            return true;
        }
        return false;
    }

    public static function getChargingRecord($serial): array
    {
        $res = self::query("charging/$serial");
        if (!$res['status']) {
            return err($res['data']['message'] ?? '请求失败！');
        }

        return (array)$res['data'];
    }
}