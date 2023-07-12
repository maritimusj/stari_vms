<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\account;

use Exception;
use RuntimeException;
use zovye\Account;
use zovye\App;
use zovye\Device;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;
use function zovye\settings;

class MoscaleAccount
{
    const API_URL = 'https://gc.goco123.com/commercial/open/getQrCode';
    const GET_LABEL_API_URL = 'https://gc.goco123.com/commercial/open/getLabel';
    const GET_REGION_API_URL = 'https://gc.moscales.com/commercial/open/getArea';

    private $app_id;
    private $app_secret;

    /**
     * Moscale Account constructor.
     * @param $app_id
     * @param $app_secret
     */
    public function __construct($app_id, $app_secret)
    {
        $this->app_id = strval($app_id);
        $this->app_secret = strval($app_secret);
    }

    public static function isAssigned(deviceModelObj $device): bool
    {
        $acc = Account::findOneFromType(Account::MOSCALE);
        if (empty($acc)) {
            return false;
        }

        return Util::isAssigned($acc->getAssignData(), $device);
    }

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::MOSCALE, Account::MOSCALE_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $v = [];

        /** @var accountModelObj $acc */
        $acc = Account::findOneFromType(Account::MOSCALE);
        if ($acc) {
            $config = $acc->settings('config', []);
            if (empty($config['appid']) || empty($config['appsecret'])) {
                return [];
            }
            //请求公锤API
            $moscale = new static($config['appid'], $config['appsecret']);
            $moscale->fetchOne($device, $user, function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Log::error('moscale_query', [
                            'query' => $request,
                            'result' => $result,
                        ]);
                    }
                }

                try {
                    if (empty($result)) {
                        throw new RuntimeException('返回数据为空！');
                    }

                    if (is_error($result)) {
                        throw new RuntimeException($result['message']);
                    }

                    if ($result['code'] != 200) {
                        throw new RuntimeException('失败，错误代码：'.$result['code']);
                    }

                    if (empty($result['data']['qrcode_url'])) {
                        throw new RuntimeException('二维码网址为空！');
                    }

                    $data = $acc->format();

                    $data['name'] = $result['data']['name'] ?: Account::MOSCALE_NAME;
                    $data['qrcode'] = $result['data']['qrcode_url'];
                    $data['descr'] = Account::replaceCode($data['descr'], 'key', strval($result['data']['rand_key']));

                    $v[] = $data;

                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Log::error('moscale', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }

        return $v;
    }

    public static function verifyData($params): array
    {
        if (!App::isMoscaleEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOneFromType(Account::MOSCALE);
        if (empty($acc)) {
            return err('找不到指定的公众号！');
        }

        $config = $acc->settings('config', []);

        if (empty($config)) {
            return err('没有配置！');
        }

        $sign_arr = [
            'nonce' => $params['nonce'],
            'timestamp' => $params['timestamp'],
            'app_secret' => $config['appsecret'],
        ];

        sort($sign_arr, SORT_STRING);

        $signature = md5(implode($sign_arr));

        if ($config['appid'] !== $params['app_id'] || $signature !== $params['signature']) {
            return err('签名校验失败！');
        }

        return ['account' => $acc];
    }

    public static function getLabelList(): array
    {
        return Util::cachedCall(30, function () {
            $acc = Account::findOneFromType(Account::MOSCALE);
            if ($acc) {
                $config = $acc->settings('config', []);
                $moscale = new static($config['appid'], $config['appsecret']);
                $result = $moscale->fetchLabelList();
                if (is_array($result['data'])) {
                    return $result['data'];
                }
            }

            return [];
        });
    }

    public static function getRegionData(): array
    {
        return Util::cachedCall(30, function () {
            $acc = Account::findOneFromType(Account::MOSCALE);
            if ($acc) {
                $config = $acc->settings('config', []);
                $moscale = new static($config['appid'], $config['appsecret']);
                $result = $moscale->fetchRegionData();
                if (is_array($result['data'])) {
                    return $result['data'];
                }
            }

            return [];
        });
    }

    public static function cb($params = [])
    {
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException($res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['auth_open_id'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['appid']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findByMoscaleKey($params['state']);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备:'.$params['state']);
                }

                $order_uid = Order::makeUID($user, $device, $params['appid']);
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }

        } catch (Exception $e) {
            Log::error('moscale', [
                'error' => '回调处理发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchLabelList(): array
    {
        $data = $this->sign([
            'app_id' => $this->app_id,
        ]);

        return Util::post(self::GET_LABEL_API_URL, $data);
    }

    public function fetchRegionData(): array
    {
        $data = $this->sign([
            'app_id' => $this->app_id,
        ]);

        return Util::post(self::GET_REGION_API_URL, $data);
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user = null, callable $cb = null): array
    {
        $key = $device->settings('extra.moscale.key', '');
        if (empty($key)) {
            $key = settings('moscale.fan.key', '');
        }

        if (empty($key)) {
            return err('没有配置设备key！');
        }

        $profile = empty($user) ? Util::fansInfo() : $user->profile();

        $params = [
            'key' => $key,
            'state' => $device->getShadowId(),
            'app_id' => $this->app_id,
            'nickname' => $profile['nickname'],
            'sex' => empty($profile['sex']) ? 3 : $profile['sex'],
            'auth_open_id' => $profile['openid'],
        ];

        $label = $device->settings('extra.moscale.label', []);
        if (isEmptyArray($label)) {
            $label = settings('moscale.fan.label', []);
        }

        if ($label) {
            $params['label'] = $label;
        }

        $region = $device->settings('extra.moscale.region', []);
        if (empty($region) || empty($region['province'])) {
            $region = settings('moscale.fan.region', []);
        }

        if ($region) {
            if ($region['province']) {
                $params['province_code'] = strval($region['province']);
            }
            if ($region['city']) {
                $params['city_code'] = strval($region['city']);
            }
            if ($region['area']) {
                $params['area_code'] = strval($region['area']);
            }
        }

        $data = $this->sign($params);

        $result = Util::post(self::API_URL, $data);

        if ($cb) {
            $cb($data, $result);
        }

        return $result;
    }

    private function sign($data)
    {
        $nonce = Util::random(16);

        $timestamp = empty($data['timestamp']) ? time() : $data['timestamp'];
        $sign_arr = [
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'app_secret' => $this->app_secret,
        ];

        sort($sign_arr, SORT_STRING);

        $signature = md5(implode($sign_arr));

        $data['timestamp'] = $timestamp;
        $data['signature'] = $signature;
        $data['nonce'] = $nonce;

        return $data;
    }
}
