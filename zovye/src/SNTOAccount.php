<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class SNTOAccount
{
    const API_URL = 'https://xf.snto.com';

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


    public static function verifyData($params): array
    {
        return [];
    }

    public static function cb($params = [])
    {
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
}
