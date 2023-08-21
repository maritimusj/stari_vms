<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\account;

use Exception;
use RuntimeException;
use zovye\Account;
use zovye\App;
use zovye\Contract\IAccountProvider;
use zovye\Device;
use zovye\HttpUtil;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\Session;
use zovye\User;
use zovye\Util;
use zovye\We7;
use function zovye\err;
use function zovye\is_error;

class MeiPaAccount implements IAccountProvider
{
    const API_URL = 'http://hz.web.meipa.net/api/qrcode/getqrcode';
    const AUTH_URL = 'http://hz.api.meipa.net/wechat/api.other/authopenid?callbackurl=';

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

    public static function getUID(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::MEIPA, Account::MEIPA_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        /** @var accountModelObj $acc */
        $acc = Account::findOneFromType(Account::MEIPA);
        if (empty($acc)) {
            return [];
        }

        $meipa_openid = $user->settings('customData.meipa.openid', '');
        if (empty($meipa_openid)) {
            $data = $acc->format();
            $data['redirect_url'] = self::AUTH_URL.urlencode(
                    Util::murl('meipa', ['op' => 'meipa_auth', 'device' => $device->getShadowId()])
                );

            return [$data];
        }

        $config = $acc->settings('config', []);
        if (empty($config['apiid']) || empty($config['appkey'])) {
            return [];
        }

        $v = [];
        //请求API
        $MeiPa = new self($config['apiid'], $config['appkey']);
        $params = [
            'meipaopenid' => $meipa_openid,
            'apiversion' => 'v2.1',
            'provincecode' => $config['region']['code']['province'] ?? '',
            'citycode' => $config['region']['code']['city'] ?? '',
            'areacode' => $config['region']['code']['area'] ?? '',
        ];
        $MeiPa->fetchOne($device, $user, $params, function ($request, $result) use ($acc, $device, $user, &$v) {
            if (App::isAccountLogEnabled()) {
                $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                if (empty($log)) {
                    Log::error('meipa_query', [
                        'query' => $request,
                        'result' => $result,
                    ]);
                }
            }

            if (is_error($result)) {
                Log::error('meipa', [
                    'user' => $user->profile(),
                    'acc' => $acc->getName(),
                    'device' => $device->profile(),
                    'error' => $result,
                ]);
            } elseif ($result['status'] == 1 && $result['data']['qrcodeurl']) {
                $data = $acc->format();

                $data['title'] = $result['data']['wechat_name'] ?: Account::MEIPA_NAME;
                $data['qrcode'] = $result['data']['qrcodeurl'];

                if ($result['data']['joburl'] && We7::starts_with($result['data']['joburl'], 'http')) {
                    $data['redirect_url'] = $result['data']['joburl'];
                }

                $data['descr'] = Account::replaceCode($data['descr'], 'code', strval($result['data']['code_words']));

                $v[] = $data;
            }
        });

        return $v;
    }

    public static function verifyData($params): array
    {
        if (!App::isMeiPaEnabled()) {
            return err('没有启用！');
        }
        $acc = Account::findOneFromType(Account::MEIPA);
        if (empty($acc)) {
            return err('找不到指定的公众号！');
        }

        $config = $acc->settings('config', []);

        if (empty($config)) {
            return err('没有配置！');
        }

        $MeiPa = new self($config['apiid'], $config['appkey']);

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

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$data['order_sn']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $data);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findOne(['shadow_id' => $data['carry_data']]);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备！');
                }

                $order_uid = Order::makeUID($user, $device, $data['order_sn']);
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $data);
            }

        } catch (Exception $e) {
            Log::error('meipa', [
                'data' => $data,
                'error' => '回调处理发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchOne(
        deviceModelObj $device,
        userModelObj $user = null,
        $params = [],
        callable $cb = null
    ): array {
        $profile = empty($user) ? Session::fansInfo() : $user->profile();

        $params = array_merge($params, [
            'apiid' => $this->api_id,
            'time' => time(),
            'openid' => $profile['openid'],
            'nickname' => $profile['nickname'],
            'headimgurl' => empty($profile['avatar']) ? $profile['headimgurl'] : $profile['avatar'],
            'sex' => $profile['sex'],
            'country' => $profile['country'],
            'province' => $profile['province'],
            'city' => $profile['city'],
            'carry_data' => $device->getShadowId(),
        ]);

        $params['sing'] = $this->sign($params);
        $result = HttpUtil::post(self::API_URL, $params, false);
        if ($cb) {
            $cb($params, $result);
        }

        return $result;
    }

    public function sign($data): string
    {
        return md5($data['time'].$data['apiid'].$this->app_key.$data['openid'].$this->app_key.$data['carry_data']);
    }
}
