<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class WeiSureAccount
{

    const ResponseOk = '{"code":"0", "msg":"成功","returnData":{}}';

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::WEISURE, Account::WEISURE_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOneFromType(Account::WEISURE);
        if (empty($acc)) {
            return [];
        }

        //每个用户限领一次
        if (Util::checkLimit($acc, $user, [], 1)) {
            return [];
        }

        $config = $acc->get('config', []);
        if (empty($config['companyId']) || isEmptyArray($config['h5url'])) {
            return [];
        }

        $user->setLastActiveDevice($device);

        try {
            $data = $acc->format();

            if (App::isAccountLogEnabled()) {
                Account::createQueryLog($acc, $user, $device, [
                    'config' => $config,
                ], null);
            }

            return [$data];

        } catch (Exception $e) {
            Log::error('weisure', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * @param $params
     * @return array
     */
    public static function verifyData($params): array
    {
        if (!App::isWeiSureEnabled()) {
            return err('没有启用！');
        }

        if (isEmptyArray($params)) {
            return err('请求数据为空！');
        }

        $account = Account::findOneFromType(Account::WEISURE);
        if (empty($account)) {
            return err('找不到指定公众号！');
        }

        return ['account' => $account];
    }

    public static function cb(array $params = [], $throw = false)
    {
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：'.$res['message']);
            }

            list($openid, $uid) = explode(':', base64_decode($params['outerUserId']));
            if (empty($openid) || empty($uid)) {
                throw new RuntimeException('回调数据不正确！');
            }

            $user = User::get($openid, true);
            if (empty($user)) {
                throw new RuntimeException('找不到这个用户！');
            }
            if ($user->isBanned()) {
                throw new RuntimeException('用户暂时不可用！');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            // 每个用户限领一次
            if (Util::checkLimit($acc, $user, [], 1)) {
                throw new RuntimeException('用户已经参加了活动！');
            }

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $uid]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:'.$uid);
            }

            $order_uid = Order::makeUID(
                $user,
                $device,
                sha1($params['outerUserId'])
            );

            Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);

        } catch (Exception $e) {
            if ($throw) {
                throw $e;
            }
            Log::error('weisure', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
        }
    }
}