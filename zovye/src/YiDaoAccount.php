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

class YiDaoAccount
{
    const API_URL = 'https://api.yidaogz.cn/open/commercial/get_qrcode';

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::YIDAO, Account::YIDAO_NAME);
    }

    public static function makeSign($nonce, $secret): string
    {
        $arr = [$nonce, $secret];
        sort($arr, SORT_STRING);

        return md5(implode($arr));
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOneFromType(Account::YIDAO);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->get('config', []);
        if (empty($config['appid'])) {
            return [];
        }

        $fans = empty($user) ? Util::fansInfo() : $user->profile();

        $data = [
            'key' => strval($config['device_key']),
            'develop_appid' => strval($config['appid']),
            'label' => intval($config['scene']),
            'ip' => Util::getClientIp(),
            'auth_open_id' => $fans['openid'],
            'nickname' => $fans['nickname'],
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'nonce' => Util::random(16, true),
            'timestamp' => TIMESTAMP,
            'state' => $device->getImei(),
        ];

        $data['signature'] = self::makeSign($data['nonce'], $config['app_secret']);

        $result = Util::post(self::API_URL, $data);

        if (App::isAccountLogEnabled()) {
            $log = Account::createQueryLog($acc, $user, $device, $data, $result);
            if (empty($log)) {
                Log::error('yidao_query', [
                    'request' => $data,
                    'result' => $result,
                ]);
            }
        }

        $v = [];

        try {
            if (empty($result)) {
                throw new RuntimeException('返回数据为空！');
            }

            if (is_error($result)) {
                throw new RuntimeException($result['message']);
            }

            if ($result['code'] == 20000) {

                $data = $result['result']['data'];
                if (isEmptyArray($data)) {
                    throw new RuntimeException('没有数据！');
                }

                $data = $acc->format();

                $data['title'] = $data['appname'] ?: Account::MENGMO_NAME;
                $data['qrcode'] = $data['qrcode_url'];
                $data['descr'] = Account::ReplaceCode($data['descr'], 'code', strval($data['code']));

                $v[] = $data;

            } elseif ($result['code'] != 50000) {
                throw new RuntimeException($result['message']);
            }

        } catch (Exception $e) {
            
            if (App::isAccountLogEnabled() && isset($log)) {
                $log->setExtraData('error_msg', $e->getMessage());
                $log->save();
            } else {
                Log::error('yidao', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $v;
    }

    public static function verifyData($params): array
    {
        if (!App::isYiDaoEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOneFromType(Account::YIDAO);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：' . $res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['auth_open_id'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('用户已被禁用！');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];
            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['openid']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::get($params['state'], true);
                if (empty($device)) {
                    throw new RuntimeException('找不对这个设备:' . $params['state']);
                }

                $order_uid = Order::makeUID($user, $device, sha1($params['openid']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }
        } catch (Exception $e) {
            Log::error('yidao', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
        }
    }
}