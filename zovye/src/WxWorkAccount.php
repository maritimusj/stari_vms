<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class WxWorkAccount extends AQIInfo
{
    const API_URL = 'https://c.api.aqiinfo.com/ChannelApi/WxworkTicket';
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
        return Account::makeThirdPartyPlatformUID(Account::WxWORK, Account::WxWORK_NAME);
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user, callable $cb = null): array
    {
        $fans = empty($user) ? Util::fansInfo() : $user->profile();

        $data = [
            'appKey' => $this->app_key,
            'exUid' => $fans['openid'],
            'vmid' => $device->getImei(),
            'time' => time(),
            'extra' => "{$device->getShadowId()}:{$fans['openid']}",
        ];

        $data['ufsign'] = self::sign($data, $this->app_secret);

        $result = Util::post(self::API_URL, $data, false);

        if ($cb) {
            $cb($data, $result);
        }

        return $result;
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

        /** @var accountModelObj $acc */
        $acc = Account::findOneFromType(Account::WxWORK);
        if ($acc) {
            $config = $acc->settings('config', []);
            if (empty($config['key']) || empty($config['secret'])) {
                return [];
            }
            //请求API
            $wxWork = new WxWorkAccount($config['key'], $config['secret']);
            $wxWork->fetchOne($device, $user, function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Log::debug('wxWork_query', [
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

                    $data = $acc->format();

                    if ($result['data']['name']) {
                        $data['name'] = $result['data']['name'];
                    }

                    $res = Util::createQrcodeFile("wxWork{$result['data']['ticket']}", $result['data']['url']);
                    if (is_error($res)) {
                        Log::error('wxWork', [
                            'error' => 'fail to createQrcode file',
                            'result' => $res,
                        ]);
                        $data['redirect_url'] = $result['data']['url'];
                    } else {
                        $data['qrcode'] = Util::toMedia($res);
                    }

                    $v[] = $data;

                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Log::error('wxWork', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }

        return $v;
    }
}