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

    const ResponseOk = '{"code":"0", "msg":"成功","returnData":"{}"}';

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

        $config = $acc->get('config', []);
        if (empty($config['companyId']) || isEmptyArray($config['url'])) {
            return [];
        }

        try {
            $data = $acc->format();

            $params = [
                'companyId' => $config['companyId'],
                'wtagid' => $config['wtagid'],
                'outerUserId' => base64_encode("{$user->getOpenid()}:{$device->getShadowId()}"),
            ];

            $url = We7::murl('weisure', $params);

            $res = Util::createQrcodeFile("weisure.{$user->getOpenid()}", $url);
            if (is_error($res)) {
                Log::error('weisure', [
                    'error' => 'fail to createQrcode file',
                    'result' => $res,
                ]);

                return [];
            } else {
                $data['qrcode'] = Util::toMedia($res);

                if (App::isAccountLogEnabled()) {
                    Account::createQueryLog($acc, $user, $device, [
                        'params' => $params,
                        'h5' => $url,
                    ], []);
                }
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

    public static function cb(array $params = [])
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

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $uid]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:'.$uid);
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            $order_uid = Order::makeUID(
                $user,
                $device,
                sha1($params['policyNo'] ?? $params['quoteNo'] ?? $params['outerUserId'])
            );

            Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);

        } catch (Exception $e) {
            Log::error('weisuire', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
        }
    }
}