<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class AQIInfo
{
    protected static $type;
    protected static $name;

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(self::$type, self::$name);
    }

    public static function verifyData($params): array
    {
        $acc = Account::findOneFromType(self::$type);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        $config = $acc->settings('config', []);
        if (empty($config)) {
            return err('没有配置！');
        }

        Util::logToFile('AQIInfo', [
            'params' => $params,
            'config' => $config,
        ]);
        //暂时无法确定验签算法
        // if ($config['key'] !== $params['appKey'] || self::sign($params, $config['secret']) !== $params['ufsign']) {
        //     return err('签名校验失败！');
        // }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：' . $res['message']);
            }

            list($shadow_id, $openid) = explode(':', $params['extra'], 2);

            /** @var userModelObj $user */
            $user = User::get($openid, true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $shadow_id]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:' . $shadow_id);
            }

            $acc = $res['account'];

            $order_uid = Order::makeUID($user, $device, strval($params['tradeNo']));

            Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);

        } catch (Exception $e) {
            Util::logToFile('AQIInfo', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public static function sign(array $data, string $secret): string
    {
        ksort($data);

        $arr = [];
        foreach ($data as $key => $val) {
            if ($key == 'ufsign') {
                continue;
            }
            $arr[] = "$key=$val";
        }

        $str = implode('&', $arr);

        return md5(hash_hmac('sha1', $str, $secret, true));
    }
}