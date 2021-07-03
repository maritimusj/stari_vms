<?php


namespace zovye;


use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\userModelObj;
use zovye\model\deviceModelObj;

class AQiinfoAccount
{
    const API_URL = 'https://c.api.aqiinfo.com/ChannelApi/UfansTicket';
    const CB_RESPONSE = '{"code":200}';

    private $app_key;
    private $app_secret;

    public function __construct(string $app_key, string $app_secret)
    {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
    }

    public static function getUid(): string
    {
        return Account::makeSpecialAccountUID(Account::AQIINFO, Account::AQIINFO_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $v = [];

        /** @var accountModelObj $acc */
        $acc = Account::findOne(['state' => Account::AQIINFO]);
        if ($acc) {
            $config = $acc->settings('config', []);
            if (empty($config['key']) || empty($config['secret'])) {
                return [];
            }
            //请求API
            $AQiinfo = new AQiinfoAccount($config['key'], $config['secret']);
            $AQiinfo->fetchOne($device, $user, function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEanbled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Util::logToFile('AQiinfo_query', [
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
                        if ($result['code'] == 450031) {
                            throw new RuntimeException('签名错误！');
                        }

                        if ($result['msg']) {
                            throw new RuntimeException($result['msg']);
                        }

                        throw new RuntimeException("接口返回错误：{$result['code']}");
                    }

                    if (empty($result['data']) || empty($result['data']['ticket']) || empty($result['data']['url'])) {
                        throw new RuntimeException('返回数据不正确！');
                    }

                    $user->set('AQiinfo', $result['data']);

                    $data = $acc->format();

                    if ($result['data']['name']) {
                        $data['name'] = $result['data']['name'];
                    }

                    $res = Util::createQrcodeFile("aqiinfo{$result['data']['ticket']}", $result['data']['url']);
                    if (is_error($res)) {
                        Util::logToFile('AQiinfo', [
                            'error' => 'fail to createQrcode file',
                            'result' => $res,
                        ]);
                        $data['redirect_url'] = $result['data']['url'];
                    } else {
                        $data['qrcode'] = Util::toMedia($res);
                    }

                    $v[] = $data;

                    if (App::isAccountLogEanbled() && isset($log)) {
                        $log->setExtraData('account', $data);
                        $log->save();
                    }

                } catch (Exception $e) {
                    if (App::isAccountLogEanbled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    }
                }
            });
        }

        return $v;
    }

    public static function verifyData($params): array
    {
        if (!App::isAQiinfoEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOne(['state' => Account::AQIINFO]);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        $config = $acc->settings('config', []);
        if (empty($config)) {
            return err('没有配置！');
        }

        Util::logToFile('AQiinfo', [
            'params' => $params,
            'config' => $config,
        ]);

        // if ($config['key'] !== $params['appKey'] || self::sign($params, $config['secret']) !== $params['ufsign']) {
        //     return err('签名校验失败！');
        // }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：' . $res['message']);
            }

            list($shadow_id, $openid) = explode(':', $params['extra'], 2);

            /** @var userModelObj $user */
            $user = User::get($openid, true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $shadow_id]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:' . $shadow_id);
            }

            $trade_no = empty($params['tradeNo']) ? Util::random(32, true) : $params['tradeNo'] . Util::random(32);
            $order_uid = substr("U{$user->getId()}D{$device->getId()}{$trade_no}", 0, MAX_ORDER_NO_LEN);

            $acc = $res['account'];

            Account::createSpecialAccountOrder($acc, $user, $device, $order_uid, $params);

        } catch (Exception $e) {
            Util::logToFile('AQiinfo', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user, callable $cb = null): array
    {
        $fans = empty($user) ? Util::fansInfo() : $user->profile();

        $data = [
            'appKey' => $this->app_key,
            'exUid' => $fans['openid'],
            'city' => str_replace('市', '', $fans['city']),
            'vmid' => $device->getImei(),
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'extra' => "{$device->getShadowId()}:{$fans['openid']}",
            'time' => time(),
        ];

        $data['ufsign'] = self::sign($data, $this->app_secret);

        $result = Util::post(self::API_URL, $data, false);

        if ($cb) {
            $cb($data, $result);
        }

        return $result;
    }

    public static function sign(array $data, string $secret): string
    {
        ksort($data);

        $arr = [];
        foreach ($data as $key => $val) {
            if ($key == 'ufsign') {
                continue;
            }
            $arr[] = "{$key}={$val}";
        }

        $str = implode('&', $arr);

        return md5(hash_hmac('sha1', $str, $secret, true));
    }
}
