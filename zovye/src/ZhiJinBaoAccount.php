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
        return Account::makeThirdPartyPlatformUID(Account::ZJBAO, Account::ZJBAO_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $v = [];

        /** @var accountModelObj $acc */
        $acc = Account::findOneFromType(Account::ZJBAO);
        if ($acc) {
            $config = $acc->settings('config', []);
            if (empty($config['key']) || empty($config['secret'])) {
                return [];
            }
            //请求API
            $ZJBao = new ZhiJinBaoAccount($config['key'], $config['secret']);
            $ZJBao->fetchOne($device, $user, [], function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Log::error('zjbao_query', [
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

                    if ($result['code'] != 0) {
                        throw new RuntimeException('失败，发生错误：' . $result['code']);
                    }

                    if (empty($result['data']) || empty($result['data']['qrcodeUrl'])) {
                        throw new RuntimeException('返回的数据不正确！');
                    }

                    $data = $acc->format();

                    $data['name'] = $result['data']['nickname'] ?: Account::ZJBAO_NAME;
                    $data['qrcode'] = $result['data']['qrcodeUrl'];

                    $v[] = $data;

                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Log::error('zjbao', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
        }

        return $v;
    }


    public static function verifyData($params): array
    {
        if (!App::isZJBaoEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOneFromType(Account::ZJBAO);
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

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$data['appId']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $data);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::get($data['deviceSn'], true);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备:' . $data['deviceSn']);
                }

                $order_uid = Order::makeUID($user, $device, sha1($data['appId']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $data);
            }

        } catch (Exception $e) {
            Log::error('zjbao', [
                'error' => '回调处理发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user = null, $params = [], callable $cb = null): array
    {
        $profile = empty($user) ? Util::fansInfo() : $user->profile();

        $params = array_merge($params, [
            'zjbAppId' => $this->app_id,
            'openId' => $profile['openid'],
            'nickName' => $profile['nickname'],
            'headUrl' => empty($profile['avatar']) ? $profile['headimgurl'] : $profile['avatar'],
            'sex' => $profile['sex'],
            'countryName' => $profile['country'],
            'provinceName' => $profile['province'],
            'cityName' => $profile['city'],
            'nonceStr' => Util::random(16, true),
            'timeStamp' => time(),
            'deviceSn' => $device->getImei(),
            'ipAddress' => $user->getLastActiveData('ip', CLIENT_IP),
            'userAgent' => $user->settings('from.user-agent', $_SERVER['HTTP_USER_AGENT']),
        ]);

        $params['scene'] = $device->settings('zjbao.scene', '');

        $area = $device->settings('extra.location.tencent.area', []);
        if (isEmptyArray($area)) {
            $area = $device->settings('extra.location.baidu.area', []);
        }

        $params['deviceCountry'] = '中国';
        $params['deviceProvince'] = strval($area[0]);
        $params['deviceCity'] = strval($area[1]);
        $params['deviceDistrict'] = strval($area[2]);

        $params['sign'] = $this->sign($params);
        $result = Util::post(self::API_URL, $params);

        if ($cb) {
            $cb($params, $result);
        }

        return $result;
    }

    private function sign($data): string
    {
        $keys = [
            'extraParam' => $data['extraParam'],
            'nonceStr' => $data['nonceStr'],
            'openId' => $data['openId'],
            'timeStamp' => $data['timeStamp'],
            'zjbAppId' => $data['zjbAppId'],
            'zjbSecret' => $this->app_secret,
        ];

        $str = [];
        foreach ($keys as $key => $val) {
            if ($val == '') {
                continue;
            }
            $str[] = "$key=$val";
        }

        return strtoupper(md5(implode('&', $str)));
    }
}
