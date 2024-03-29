<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\util;

use we7\ihttp;
use zovye\Config;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\We7;
use function zovye\err;
use function zovye\getArray;
use function zovye\is_error;
use function zovye\settings;

class LocationUtil
{
    public static function getLBSKey()
    {
        return settings('user.location.appkey', DEFAULT_LBS_KEY);
    }

    /**
     * 获取ip地址定位信息
     */
    public static function getIpInfo($ip)
    {
        $data = We7::cache_read($ip);
        if ($data) {
            return $data;
        }

        $lbs_key = self::getLBSKey();
        $url = "https://apis.map.qq.com/ws/location/v1/ip?ip=$ip&key=$lbs_key";

        $resp = ihttp::get($url);

        if (!is_error($resp)) {
            $res = json_decode($resp['content'], true);

            if ($res && $res['status'] == 0 && is_array($res['result'])) {

                $data = $res['result'];

                $data['data'] = [
                    'province' => $data['ad_info']['province'],
                    'city' => $data['ad_info']['city'],
                    'district' => $data['ad_info']['district'],
                ];

                We7::cache_write($ip, $data);

                return $data;
            }
        }

        return [];
    }

    public static function getData($lng, $lat): array
    {
        $lbs_key = self::getLBSKey();
        $url = 'https://apis.map.qq.com/ws/geocoder/v1/?';
        $params = urlencode("location=$lat,$lng&key=$lbs_key&get_poi=0");

        $resp = ihttp::get($url.$params);

        if (!is_error($resp)) {
            $res = json_decode($resp['content'], true);
            if ($res) {
                if ($res['status'] == 0 && is_array($res['result']['address_component'])) {
                    return [
                        'province' => $res['result']['address_component']['province'],
                        'city' => $res['result']['address_component']['city'],
                        'district' => $res['result']['address_component']['district'],
                        'address' => $res['result']['address'],
                    ];
                }
            }
        }

        return [];
    }

    public static function getDistance($from, $to, $mode = 'walking')
    {
        $lbs_key = self::getLBSKey();
        $url = "https://apis.map.qq.com/ws/distance/v1/matrix?mode=$mode&from={$from['lat']},{$from['lng']}&to={$to['lat']},{$to['lng']}&key=$lbs_key";
        $resp = ihttp::get($url);

        if (is_error($resp)) {
            return $resp;
        }

        parse_str(str_replace('; ', '&', getArray($resp, 'headers.X-LIMIT', '')), $limits);

        if (is_array($limits)) {
            $limits['updatetime'] = time();
            Config::location('tencent.lbs.limits', $limits, true);
        }

        $res = json_decode($resp['content'], true);
        if (empty($res)) {
            return err('请求失败，返回数据为空！');
        }

        if ($res['status'] != 0) {
            return err($res['message']);
        }

        if (is_array($res['result']['rows'])) {
            return intval(getArray($res, 'result.rows.0.elements.0.distance'));
        }

        return err('未知错误！');
    }

    /**
     * 是否需要对用户进行定位操作
     */
    public static function mustValidate(userModelObj $user, deviceModelObj $device): bool
    {
        return ($user->isWxUser() || $user->isWXAppUser() || $user->isDouYinUser())
            && $device->needValidateLocation()
            && time() - $user->getLastActiveData('location.time', 0) > settings('user.scanAlive', VISIT_DATA_TIMEOUT)
            && !$user->getLastActiveData('location.validated');
    }
}