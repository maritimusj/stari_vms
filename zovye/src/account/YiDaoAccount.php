<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\account;

use Exception;
use RuntimeException;
use zovye\App;
use zovye\contract\IAccountProvider;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\HttpUtil;
use zovye\util\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class YiDaoAccount implements IAccountProvider
{
    const API_URL = 'https://api.yidaogz.cn/open/commercial/get_qrcode';

    public static function getUID(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::YIDAO, Account::YIDAO_NAME);
    }

    public static function makeSign($arr = []): string
    {
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

        $fans = $user->profile();

        $data = [
            'key' => strval($config['device_key']),
            'develop_appid' => strval($config['appid']),
            'label' => intval($config['scene']),
            'ip' => CLIENT_IP,
            'auth_open_id' => $fans['openid'],
            'nickname' => $fans['nickname'],
            'sex' => $fans['sex'] ?? 0,
            'nonce' => Util::random(16, true),
            'timestamp' => TIMESTAMP,
            'state' => $device->getImei().':'.Util::random(16),
        ];

        $data['signature'] = self::makeSign([
            $data['timestamp'],
            $data['nonce'],
            $config['app_secret'],
        ]);

        $result = HttpUtil::post(self::API_URL, $data);

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

                $res = $result['data'];
                if (isEmptyArray($res) || empty($res['qrcode_url'])) {
                    throw new RuntimeException('没有数据！');
                }

                $data = $acc->format();

                $data['title'] = $res['appname'] ?: Account::YIDAO_NAME;
                $data['qrcode'] = $res['qrcode_url'];
                $data['descr'] = Account::replaceCode($data['descr'], 'code', strval($res['code']), false);

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
                    'error' => $e->getMessage(),
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

        $config = $acc->get('config', []);
        if (isEmptyArray(
                $config
            ) || $params['develop_appid'] !== $config['appid'] || $params['key'] !== $config['device_key']) {
            return err('数据检验失败！');
        }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：'.$res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['auth_open_id'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('用户已被禁用！');
            }

            list($device_imei, $once_str) = explode(':', $params['state'], 2);
            $once_str = $once_str ?? time();
            /** @var accountModelObj $acc */
            $acc = $res['account'];
            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}$once_str");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::get($device_imei, true);
                if (empty($device)) {
                    throw new RuntimeException('找不对这个设备:'.$device_imei);
                }

                $order_uid = Order::makeUID($user, $device, sha1($once_str));
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