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
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class YfbAccount implements IAccountProvider
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

    public static function getUID(): string
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

        $result = HttpUtil::post(self::API_URL, $data);

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
                return err('没有配置！');
            }

            //请求对方API
            $obj = new self($config['id'], $config['secret'], $config['key'], $config['scene']);
        }

        return $obj;
    }

    /**
     * 请求一个公众号
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return array
     */
    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $v = [];

        $account = Account::findOneFromType(Account::YFB);
        if ($account) {
            //请求对方API
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
                        throw new RuntimeException('返回数据为空！');
                    }

                    if (is_error($result)) {
                        throw new RuntimeException($result['message']);
                    }

                    if (!empty($result['code'])) {
                        if ($result['code'] == 6007) {
                            throw new RuntimeException('暂时没有公众号！');
                        }
                        throw new RuntimeException('失败，错误代码：'.$result['code']);
                    }

                    $qrcode = json_decode($result['data'], true);
                    if (empty($qrcode) || empty($qrcode['qrCode'])) {
                        throw new RuntimeException('返回的二维码数据为空！');
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
            return err('没有启用！');
        }

        if (isEmptyArray($params)) {
            return err('请求数据为空！');
        }

        $account = Account::findOneFromType(Account::YFB);
        if (empty($account)) {
            return err('找不到指定公众号！');
        }

        $yfb = self::getYFB($account);
        if (is_error($yfb)) {
            return $yfb;
        }

        if ($yfb->sign($params) !== $params['sign']) {
            return err('签名检验失败！');
        }

        return ['account' => $account];
    }

    public static function cb($params = [])
    {
        //出货流程
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：'.$res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['openId'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['mpAppId']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findOne(['shadow_id' => $params['params']]);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备:'.$params['params']);
                }

                $order_uid = Order::makeUID($user, $device, sha1($params['mpAppId']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }

        } catch (Exception $e) {
            Log::error('yfb', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }
}