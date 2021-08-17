<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class SNTOAccount
{
    const API_URL = 'https://xf.snto.com';
    const REPONSE_STR = 'Ok';

    private $id;
    private $key;
    private $channel;
    private $token;

    /**
     * KingFansAccount constructor.
     * @param $id
     * @param $key
     * @param $channel
     * @param string $token
     */
    public function __construct($id, $key, $channel, string $token = '')
    {
        $this->id = $id;
        $this->key = $key;
        $this->channel = $channel;
        $this->token = $token;
    }

    public static function getUid(): string
    {
        return Account::makeSpecialAccountUID(Account::SNTO, Account::SNTO_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOne(['state' => Account::SNTO]);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->settings('config', []);
        if (empty($config['id']) || empty($config['key'])) {
            return [];
        }

        $obj = new SNTOAccount($config['id'], $config['key'], $config['channel']);

        //获取token
        if (isEmptyArray($config['data']) || time() - $config['data']['last'] > $config['data']['expire_in'] - 60) {
            $res = $obj->fetchToken();
            if (!is_error($res) && $res['code'] == 200) {
                $config['data'] = $res['data'];
                $config['data']['last'] = time();
                $acc->updateSettings('config', $config);
            } else {
                Util::logToFile('snto_error', $res);
            }
        }

        if (empty($config['data']['token'])) {
            return [];
        }

        $obj->token = $config['data']['token'];

        $v = [];

        $obj->fetchOne($device, $user, function ($request, $result) use ($acc, $device, $user, &$v) {
            if (App::isAccountLogEnabled()) {
                $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                if (empty($log)) {
                    Util::logToFile('snto_query', [
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

                if ($result['code'] !== 200) {
                    throw new RuntimeException("请求失败，错误：{$result['message']}");
                }

                if (empty($result['data']) || empty($result['data']['qr_code_url'])) {
                    throw new RuntimeException("请求失败，返回数据为空！");
                }

                $data = $acc->format();

                $data['name'] = $result['data']['app_name'];
                $data['title'] = $result['data']['app_name'];
                $data['qrcode'] = $result['data']['qr_code_url'];

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
                    Util::logToFile('snto', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        });

        return $v;
    }


    public static function verifyData($data): array
    {
        if (!App::isSNTOEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOne(['state' => Account::SNTO]);
        if (empty($acc)) {
            return err('找不到指定的公众号！');
        }

        $config = $acc->settings('config', []);

        if (empty($config)) {
            return err('没有配置！');
        }

        $obj = new SNTOAccount($config['id'], $config['key'], $config['channel']);
        if ($obj->sign($data) !== $data['sign']) {
            return err('签名校验失败！');
        }

        return ['account' => $acc];
    }

    public static function cb($data = [])
    {
        Util::logToFile('snto', $data);

        try {
            $res = self::verifyData($data);
            if (is_error($res)) {
                throw new RuntimeException($res['message']);
            }

            list($app, $device_uid, $openid) = explode(':', $data['mac']);
            if ($app !== App::uid(6)) {
                throw new RuntimeException('不正确的调用！');
            }

            /** @var userModelObj $user */
            $user = User::get($openid, true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用！');
            }

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $device_uid]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:' . $device_uid);
            }

            $acc = $res['account'];

            $order_uid = Order::makeUID($user, $device, sha1($data['order_id']));

            Account::createSpecialAccountOrder($acc, $user, $device, $order_uid, $data);
        } catch (Exception $e) {
            Util::logToFile('snto', [
                'error' => '回调处理发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchToken()
    {
        $url = self::API_URL . '/token/scanQr.json?' . http_build_query([
            'app_id' => $this->id,
            'app_key' => $this->key,
        ]);
        return Util::getJSON($url);
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user, callable $cb = null)
    {
        $url = self::API_URL . '/v2/resource.json';

        $fans = empty($user) ? Util::fansInfo() : $user->profile();
        $uid = App::uid(6);
        $data = [
            'channel' => $this->channel,
            'mac' => "{$uid}:{$device->getShadowId()}:{$user->getOpenid()}",
            'nickname' => $fans['nickname'],
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'ip' => CLIENT_IP,
        ];

        $result = Util::post($url, $data, true, 3, [
            CURLOPT_HTTPHEADER => ["AUTH: {$this->token}"],
        ]);

        if ($cb) {
            $cb($data, $result);
        }
    }

    public function sign($data = []): string
    {
        return sha1($data['app_id'] . $data['order_id'] . $data['mac'] . $this->key);
    }
}
