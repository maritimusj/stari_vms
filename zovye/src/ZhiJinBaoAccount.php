<?php


namespace zovye;


use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class ZhiJinBaoAccount
{
    const API_URL = 'https://api.zhijinbao.net/gzh/api/v1/getGzhInfo';
    const RESPONSE = '{"msg":"success","code":0}';

    private $app_id;
    private $app_secret;

    /**
     * ZhiJinBaoAccount constructor.
     * @param $app_id
     * @param $app_secret
     */
    public function __construct($app_id, $app_secret)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
    }


    public static function getUid(): string
    {
        return Account::makeSpecialAccountUID(Account::ZJBAO, Account::ZJBAO_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        /** @var accountModelObj $acc */
        $acc = Account::findOne(['state' => Account::ZJBAO]);
        if ($acc) {
            $config = $acc->settings('config', []);
            //请求API
            $ZJBao = new ZhiJinBaoAccount($config['key'], $config['secret']);
            $result = $ZJBao->fetchOne($device, $user);
            if (is_error($result) || $result['code'] != 0) {
                Util::logToFile('zjbao', [
                    'user' => $user->profile(),
                    'acc' => $acc->getName(),
                    'device' => $device->profile(),
                    'error' => $result,
                ]);
            } else {
                $data = $acc->format();

                $data['name'] = $result['nickname'];
                $data['qrcode'] = $result['qrcodeUrl'];

                return [$data];
            }
        }

        return [];
    }


    public static function verifyData($params): array
    {
        if (!App::isZJBaoEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOne(['state' => Account::ZJBAO]);
        if (empty($acc)) {
            return err('找不到指定的公众号！');
        }

        $config = $acc->settings('config', []);

        if (empty($config)) {
            return err('没有配置！');
        }

        $ZJBao = new ZhiJinBaoAccount($config['key'], $config['secret']);

        if ($params['zjbAppId'] !== $ZJBao->app_id || $ZJBao->sign($params) !== $params['sign']) {
            return err('签名校验失败！');
        }

        return ['account' => $acc];
    }

    public static function cb($data = [])
    {
        try {
            $res = self::verifyData($data);
            if (is_error($res)) {
                throw new RuntimeException($res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($data['openId'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var deviceModelObj $device */
            $device = Device::get($data['deviceSn'], true);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:' . $data['deviceSn']);
            }

            $order_uid = substr("U{$user->getId()}D{$device->getId()}{$data['sign']}", 0, MAX_ORDER_NO_LEN);

            $acc = $res['account'];

            Job::createSpecialAccountOrder([
                'device' => $device->getId(),
                'user' => $user->getId(),
                'account' => $acc->getId(),
                'orderUID' => $order_uid,
            ]);

        } catch (Exception $e) {
            Util::logToFile('zjbao', [
                'error' => '回调处理发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user = null, $params = []): array
    {
        $profile = empty($user) ? Util::fansInfo() : $user->profile();

        $params = array_merge($params, [
            'zjbAppId' => '',
            'openId' => $profile['openid'],
            'nickName' => $profile['nickname'],
            'headUrl' => empty($profile['avatar']) ? $profile['headimgurl'] : $profile['avatar'],
            'sex' => $profile['sex'],
            'countryName' => $profile['country'],
            'provinceName' => $profile['province'],
            'cityName' => $profile['city'],
            'nonceStr' => Util::random(16, true),
            'timeStamp' => time(),
            'deviceSn' => $device ? $device->getImei() : '',
        ]);

        $params['sign'] = $this->sign($params);
        $result = Util::post(self::API_URL, $params);

        Util::logToFile('zjbao_query', [
            'query' => $params,
            'result' => $result,
        ]);

        return $result;
    }

    private function sign($data): string
    {
        unset($data['sign']);

        $data['zjbSecret'] = $this->app_secret;

        sort($data);

        return strtoupper(md5(http_build_query($data)));
    }
}