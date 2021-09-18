<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class YfbAccount
{
    const API_URL = 'http://yb001.ybaokj.cn/mache/getQrCode';

    private $app_id;
    private $app_secret;
    private $scene;

    /**
     * @param $app_id
     * @param $app_secret
     * @param $scene
     */
    public function __construct($app_id, $app_secret, $scene)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
        $this->scene = $scene;
    }

    public static function getUid(): string
    {
        return Account::makeSpecialAccountUID(Account::YFB, Account::YFB_NAME);
    }

    public function getQRCode(deviceModelObj $device, userModelObj $user, callable $cb = null): array
    {
        $scene = $device->settings('extra.yfb.scene', '');
        if (empty($scene)) {
            $scene = $this->scene;
        }

        $fans = empty($user) ? Util::fansInfo() : $user->profile();

        $data = [
            'appId' => $this->app_id,
            'gender' => empty($fans['sex']) ? 0 : $fans['sex'],
            'openid' => $user->getOpenid(),
            'ip' => $user->getLastActiveData('ip', CLIENT_IP),
            'macheNumber' => $device->getShadowId(),
            'scene' => $scene,
            'timeStamp' => time(),
        ];
        $data['sign'] = $this->sign($data);

        if (!empty($scene)) {
            $data['scene'] = $scene;
        }

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
        return md5(implode('&', array_values($arr)) . $this->app_secret);
    }

    private static function getYFB(accountModelObj $acc)
    {
        static $obj = null;
        if (empty($obj)) {
            $config = $acc->settings('config', []);
            if (empty($config) || empty($config['id'] || empty($config['secret']))) {
                return err('没有配置！');
            }

            //请求对方API
            $obj = new YfbAccount($config['id'], $config['secret'], $config['scene']);
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
                        Util::logToFile('yfb_query', [
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

                    $data = $acc->format();

                    $data['qrcode'] = $result['data']['qrCode'];

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
            $device = Device::findOne(['shadow_id' => $params['macheNumber']]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:' . $params['macheNumber']);
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