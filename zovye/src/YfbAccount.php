<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class YfbAccount
{
    //const API_URL = 'http://plwxmp.ybaokj.cn/mache/getQrCode';
    const API_URL = 'http://plwxmp.ybaokj.cn/mache/getQrCodes';

    private $app_id;
    private $app_secret;
    private $scene;
    private $key;

    /**
     * @param $app_id
     * @param $app_secret
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
        return Account::makeSpecialAccountUID(Account::YFB, Account::YFB_NAME);
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
            'ip' => $user->getLastActiveData('ip', CLIENT_IP),
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
        $str = implode('&', array_values($arr)) . $this->app_secret;
        Util::logToFile('yfb', [
            'str' => $str,
            'arr' => array_values($arr),
        ]);
        return md5($str);
    }

    private static function getYFB(accountModelObj $acc)
    {
        static $obj = null;
        if (empty($obj)) {
            $config = $acc->settings('config', []);
            if (isEmptyArray($config)) {
                return err('没有配置！');
            }

            //请求对方API
            $obj = new YfbAccount($config['id'], $config['secret'], $config['key'], $config['scene']);
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

        $acc = Account::findOne(['state' => Account::YFB]);
        if ($acc) {
            //请求对方API
            $yfb = self::getYFB($acc);

            $yfb->getQRCode($device, $user, function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Util::logToFile('yfb', [
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
                        throw new RuntimeException('失败，错误代码：' . $result['code']);
                    }

                    $qrcode = json_decode($result['data'], true);
                    if (empty($qrcode) || empty($qrcode['qrCode'])) {
                        throw new RuntimeException('返回的二维码数据为空！');
                    }

                    $data = $acc->format();
                    $data['qrcode'] = $qrcode['qrCode'];
                    Util::logToFile('yfb', $data);

                    $v[] = $data;

                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('account', $data);
                        $log->save();
                    }
                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Util::logToFile('yfb', [
                            'error' => $e->getMessage()
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

        $acc = Account::findOne(['state' => Account::YFB]);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        $yfb = self::getYFB($acc);

        if ($yfb->sign($params) !== $params['sign']) {
            return err('签名检验失败！');
        }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        //出货流程
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：' . $res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['openId'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $params['params']]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:' . $params['params']);
            }

            $acc = $res['account'];

            $order_uid = Order::makeUID($user, $device);

            Account::createSpecialAccountOrder($acc, $user, $device, $order_uid, $params);

        } catch (Exception $e) {
            Util::logToFile('yfb', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }
}