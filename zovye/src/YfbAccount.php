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

class YfbAccount
{
    const API_URL = 'http://plwxmp.ybaokj.cn/mache/getQrCode';
    //const API_URL = 'http://plwxmp.ybaokj.cn/mache/getQrCodes';

    private $app_id;
    private $app_secret;
    private $scene;
    private $key;

    /**
     * @param $app_id
     * @param $app_secret
     * @param $key
     * @param $scene
     */
    public function __construct($app_id, $app_secret, $key, $scene)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
        $this->key = $key;
        $this->scene = $scene;
    }

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::YFB, Account::YFB_NAME);
    }

    public function getQRCode(deviceModelObj $device, userModelObj $user, callable $cb = null): array
    {
        $key = $device->settings('extra.yfb.key', '');
        if (empty($key)) {
            $key = $this->key;
        }

        $scene = $device->settings('extra.yfb.scene', '');
        if (empty($scene)) {
            $scene = $this->scene;
        }

        $fans = $user->profile();

        $data = [
            'appId' => $this->app_id,
            'gender' => empty($fans['sex']) ? 0 : $fans['sex'],
            'openid' => $user->getOpenid(),
            'ip' => $user->getLastActiveIp(),
            'macheNumber' => $key,
            'scene' => $scene,
            'params' => $device->getShadowId(),
            'timeStamp' => time(),
        ];

        $data['sign'] = $this->sign($data);

        $result = Util::post(self::API_URL, $data);

        if ($cb) {
            $cb($data, $result);
        }

        return $result;
    }

    public function sign($data): string
    {
        $arr = [];
        foreach ($data as $name => $val) {
            if (empty($name) || $name == 'sign' || $name == 'class' || $name == 'adminId') {
                continue;
            }
            $arr[$name] = "$name=$val";
        }

        ksort($arr);

        $str = implode('&', array_values($arr)).$this->app_secret;

        Log::debug('yfb', [
            'str' => $str,
            'arr' => array_values($arr),
        ]);

        return md5($str);
    }

    private static function getYFB(accountModelObj $account)
    {
        static $obj = null;
        if (empty($obj)) {
            $config = $account->settings('config', []);
            if (isEmptyArray($config)) {
                return err('???????????????');
            }

            //????????????API
            $obj = new YfbAccount($config['id'], $config['secret'], $config['key'], $config['scene']);
        }

        return $obj;
    }

    /**
     * ?????????????????????
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return array
     */
    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $v = [];

        $account = Account::findOneFromType(Account::YFB);
        if ($account) {
            //????????????API
            $yfb = self::getYFB($account);
            if (is_error($yfb)) {
                Log::warning('yfb_query', $yfb);

                return $v;
            }

            $yfb->getQRCode($device, $user, function ($request, $result) use ($account, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($account, $user, $device, $request, $result);
                    if (empty($log)) {
                        Log::error('yfb_query', [
                            'query' => $request,
                            'result' => $result,
                        ]);
                    }
                }

                try {
                    if (empty($result)) {
                        throw new RuntimeException('?????????????????????');
                    }

                    if (is_error($result)) {
                        throw new RuntimeException($result['message']);
                    }

                    if (!empty($result['code'])) {
                        if ($result['code'] == 6007) {
                            throw new RuntimeException('????????????????????????');
                        }
                        throw new RuntimeException('????????????????????????'.$result['code']);
                    }

                    $qrcode = json_decode($result['data'], true);
                    if (empty($qrcode) || empty($qrcode['qrCode'])) {
                        throw new RuntimeException('?????????????????????????????????');
                    }

                    $data = $account->format();
                    $data['qrcode'] = $qrcode['qrCode'];

                    $v[] = $data;

                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Log::error('yfb', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }

        return $v;
    }

    /**
     * @param $params
     * @return array
     */
    public static function verifyData($params): array
    {
        if (!App::isYFBEnabled()) {
            return err('???????????????');
        }

        if (isEmptyArray($params)) {
            return err('?????????????????????');
        }

        $account = Account::findOneFromType(Account::YFB);
        if (empty($account)) {
            return err('???????????????????????????');
        }

        $yfb = self::getYFB($account);
        if (is_error($yfb)) {
            return $yfb;
        }

        if ($yfb->sign($params) !== $params['sign']) {
            return err('?????????????????????');
        }

        return ['account' => $account];
    }

    public static function cb($params = [])
    {
        //????????????
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('???????????????'.$res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['openId'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('???????????????????????????????????????');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['mpAppId']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '???????????????????????????');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findOne(['shadow_id' => $params['params']]);
                if (empty($device)) {
                    throw new RuntimeException('????????????????????????:'.$params['params']);
                }

                $order_uid = Order::makeUID($user, $device, sha1($params['mpAppId']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }

        } catch (Exception $e) {
            Log::error('yfb', [
                'error' => '????????????! ',
                'result' => $e->getMessage(),
            ]);
        }
    }
}