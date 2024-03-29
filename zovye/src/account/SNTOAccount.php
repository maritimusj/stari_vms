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
use zovye\Session;
use zovye\util\HttpUtil;
use zovye\util\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class SNTOAccount implements IAccountProvider
{
    const API_URL = 'https://xf.snto.com';
    const RESPONSE_STR = 'Ok';

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

    public static function getUID(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::SNTO, Account::SNTO_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOneFromType(Account::SNTO);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->settings('config', []);
        if (empty($config['id']) || empty($config['key'])) {
            return [];
        }

        $obj = new self($config['id'], $config['key'], $config['channel']);

        //获取token
        if (isEmptyArray($config['data']) || time() - $config['data']['last'] > $config['data']['expire_in'] - 60) {
            $res = $obj->fetchToken();
            if (!is_error($res) && $res['code'] == 200) {
                $config['data'] = $res['data'];
                $config['data']['last'] = time();
                $acc->updateSettings('config', $config);
            } else {
                Log::error('snto_error', $res);
            }
        }

        if (empty($config['data']['token'])) {
            Log::error('snto', '无法获取token！');

            return [];
        }

        $snto_openid = $user->settings('customData.snto.openid', '');
        if (empty($snto_openid)) {
            $auth_url = self::API_URL.'/v3/qrcode/userAuth.json?';

            $data = $acc->format();
            $data['redirect_url'] = $auth_url.http_build_query([
                    'redirectUrl' => Util::murl('snto', ['op' => 'snto_auth', 'device' => $device->getShadowId()]),
                    'channel' => $config['channel'],
                    'mac' => $user->getOpenid(),
                ]);

            return [$data];
        }

        $obj->token = $config['data']['token'];

        $v = [];

        $obj->fetchOne($device, $user, $snto_openid, function ($request, $result) use ($acc, $device, $user, &$v) {
            if (App::isAccountLogEnabled()) {
                $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                if (empty($log)) {
                    Log::debug('snto_query', [
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
                    if ($result['code'] == 400003) {
                        $acc->updateSettings('config.data', []);
                    }
                    throw new RuntimeException("请求失败，错误：{$result['message']}");
                }

                if (empty($result['data']) || empty($result['data']['qr_code_url'])) {
                    throw new RuntimeException("请求失败，返回数据为空！");
                }

                $data = $acc->format();

                $data['name'] = $result['data']['app_name'] ?: Account::SNTO_NAME;
                $data['title'] = $result['data']['app_name'] ?: Account::SNTO_NAME;
                $data['qrcode'] = $result['data']['qr_code_url'];
                $data['descr'] = Account::replaceCode($data['descr'], 'key', strval($result['data']['keyword']));

                $v[] = $data;

            } catch (Exception $e) {
                if (App::isAccountLogEnabled() && isset($log)) {
                    $log->setExtraData('error_msg', $e->getMessage());
                    $log->save();
                } else {
                    Log::error('snto', [
                        'error' => $e->getMessage(),
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

        $acc = Account::findOneFromType(Account::SNTO);
        if (empty($acc)) {
            return err('找不到指定的公众号！');
        }

        $config = $acc->settings('config', []);

        if (empty($config)) {
            return err('没有配置！');
        }

        $obj = new self($config['id'], $config['key'], $config['channel']);
        if ($obj->sign($data) !== $data['sign']) {
            return err('签名校验失败！');
        }

        return ['account' => $acc];
    }

    public static function cb($data = [])
    {
        Log::debug('snto', $data);

        try {
            $res = self::verifyData($data);
            if (is_error($res)) {
                throw new RuntimeException($res['message']);
            }

            list($app, $device_uid, $openid) = explode(':', $data['params']);
            if ($app !== App::uid(6)) {
                throw new RuntimeException('不正确的调用！');
            }

            /** @var userModelObj $user */
            $user = User::get($openid, true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用！');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$data['order_id']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $data);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findOne(['shadow_id' => $device_uid]);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备:'.$device_uid);
                }

                $order_uid = Order::makeUID($user, $device, sha1($data['order_id']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $data);
            }
        } catch (Exception $e) {
            Log::error('snto', [
                'error' => '回调处理发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchToken()
    {
        $url = self::API_URL.'/token/scanQr.json?'.http_build_query([
                'app_id' => $this->id,
                'app_key' => $this->key,
            ]);

        return HttpUtil::getJSON($url);
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user = null, $snto_openid = '', callable $cb = null)
    {
        $url = self::API_URL.'/v3/qrcode.json';

        $fans = empty($user) ? Session::fansInfo() : $user->profile();
        $uid = App::uid(6);
        $data = [
            'stOpenId' => $snto_openid,
            'channel' => $this->channel,
            'mac' => $user->getOpenid(),
            'nickname' => $fans['nickname'],
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'ip' => CLIENT_IP,
            'params' => "$uid:{$device->getShadowId()}:{$user->getOpenid()}",
        ];

        $result = HttpUtil::post($url, $data, true, 3, [
            CURLOPT_HTTPHEADER => ["AUTH: $this->token"],
        ]);

        if ($cb) {
            $cb($data, $result);
        }
    }

    public function sign($data = []): string
    {
        return sha1($data['app_id'].$data['order_id'].$data['params'].$this->key);
    }
}
