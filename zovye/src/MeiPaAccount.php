<?php


namespace zovye;


use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class MeiPaAccount
{
    const API_URL = 'http://hz.web.meipa.net/api/qrcode/getqrcode';

    private $api_id;
    private $app_key;

    /**
     * MeiPaAccount constructor.
     * @param $api_id
     * @param $app_key
     */
    public function __construct($api_id, $app_key)
    {
        $this->api_id = $api_id;
        $this->app_key = $app_key;
    }

    public static function getUid(): string
    {
        return Account::makeSpecialAccountUID(Account::MEIPA, Account::MEIPA_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $v = [];

        /** @var accountModelObj $acc */
        $acc = Account::findOne(['state' => Account::MEIPA]);
        if ($acc) {
            $config = $acc->settings('config', []);
            if (empty($config['apiid']) || empty($config['appkey'])) {
                return [];
            }

            //请求API
            $MeiPa = new MeiPaAccount($config['apiid'], $config['appkey']);
            $MeiPa->fetchOne($device, $user, [], function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEanbled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Util::logToFile('meipa_query', [
                            'query' => $request,
                            'result' => $result,
                        ]);
                    }
                }

                if (is_error($result) || $result['status'] != 1) {
                    Util::logToFile('meipa', [
                        'user' => $user->profile(),
                        'acc' => $acc->getName(),
                        'device' => $device->profile(),
                        'error' => $result,
                    ]);
                } else {
                    $data = $acc->format();

                    $data['title'] = $result['data']['wechat_name'];
                    $data['qrcode'] = $result['data']['qrcodeurl'];

                    if (App::isAccountLogEanbled() && isset($log)) {
                        $log->setExtraData('account', $data);
                        $log->save();
                    }

                    $v[] = $data;
                }
            });
        }
        
        return $v;
    }

    public static function verifyData($params): array
    {
        if (!App::isMeiPaEnabled()) {
            return err('没有启用！');
        }
        $acc = Account::findOne(['state' => Account::MEIPA]);
        if (empty($acc)) {
            return err('找不到指定的公众号！');
        }

        $config = $acc->settings('config', []);

        if (empty($config)) {
            return err('没有配置！');
        }

        $MeiPa = new MeiPaAccount($config['apiid'], $config['appkey']);

        if ($params['apiid'] !== $MeiPa->api_id || $MeiPa->sign($params) !== $params['sing']) {
            return err('签名检验失败！');
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
            $user = User::get($data['openid'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用！');
            }

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $data['carry_data']]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备！');
            }

            $acc = $res['account'];

            $order_uid = Order::makeUID($user, $device, $data['order_sn']);

            Account::createSpecialAccountOrder($acc, $user, $device, $order_uid, $data);

        } catch (Exception $e) {
            Util::logToFile('meipa', [
                'data' => $data,
                'error' => '回调处理发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user = null, $params = [], callable $cb = null): array
    {
        $profile = empty($user) ? Util::fansInfo() : $user->profile();

        $params = array_merge($params, [
            'apiid' => $this->api_id,
            'time' => time(),
            'openid' => $profile['openid'],
            'nickname' => $profile['nickname'],
            'headimgurl' => empty($profile['avatar']) ? $profile['headimgurl'] : $profile['avatar'],
            'sex' => $profile['sex'],
            'province' => $profile['province'],
            'city' => $profile['city'],
            'carry_data' => $device->getShadowId(),
        ]);

        $params['sing'] = $this->sign($params);
        $result = Util::post(self::API_URL, $params, false);
        if ($cb) {
            $cb($params, $result);
        }
        return $result;
    }

    public function sign($data): string
    {
        return md5($data['time'] . $data['apiid'] . $this->app_key . $data['openid'] . $this->app_key . $data['carry_data']);
    }
}
