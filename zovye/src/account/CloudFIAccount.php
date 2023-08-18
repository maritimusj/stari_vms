<?php

namespace zovye\account;

use Exception;
use RuntimeException;
use zovye\Account;
use zovye\App;
use zovye\Contract\IAccountProvider;
use zovye\HttpUtil;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\Session;
use zovye\User;
use function zovye\err;
use function zovye\is_error;

class CloudFIAccount implements IAccountProvider
{
    const API_URL = "https://www.cloudfi.cn/index.php/Interface/zk/getZkOrder";

    private $key;
    private $channel;
    private $scene;
    private $area;

    /**
     * @param $key
     * @param $channel
     * @param $scene
     * @param $area
     */
    public function __construct($key, $channel, $scene, $area)
    {
        $this->key = $key;
        $this->channel = $channel;
        $this->scene = $scene;
        $this->area = $area;
    }

    public static function getUID(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::CloudFI, Account::CloudFI_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOneFromType(Account::CloudFI);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->settings('config', []);
        if (empty($config['key']) || empty($config['channel'])) {
            return [];
        }

        $v = [];

        (new self($config['key'], $config['channel'], $config['scene'], $config['area']))->fetchOne(
            $device,
            $user,
            function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Log::error('cloudFI_query', [
                            'query' => $request,
                            'result' => $result,
                        ]);
                    }
                }

                try {
                    if (empty($result) || empty($result['bizContent'])) {
                        throw new RuntimeException('返回数据为空！');
                    }

                    if (is_error($result)) {
                        throw new RuntimeException($result['message']);
                    }

                    if ($result['code'] != 200) {
                        throw new RuntimeException("请求失败，错误信息：{$result['message']}");
                    }

                    if (empty($result['bizContent']['qrcodeUrl'])) {
                        throw new RuntimeException('二维码为空！');
                    }

                    $data = $acc->format();

                    $data['name'] = $result['bizContent']['appname'] ?: Account::CloudFI_NAME;
                    $data['qrcode'] = $result['bizContent']['qrcodeUrl'];

                    $v[] = $data;

                    $user->setLastActiveDevice($device);

                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Log::error('cloudFI', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        );

        return $v;
    }

    public static function verifyData($params): array
    {
        $acc = Account::findOneFromType(Account::CloudFI);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        $config = $acc->settings('config', []);
        if (empty($config)) {
            return err('没有配置！');
        }

        // if (md5($params['openid'] . $params['timestamp'] . $config['key']) !== $params['sign']) {
        //     return err('签名检验失败！');
        // }

        return ['account' => $acc];
    }

    public static function cb(array $params = [])
    {
        //出货流程
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：'.$res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['openid'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['zkOpenid']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = $user->getLastActiveDevice();
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备!');
                }

                $order_uid = Order::makeUID($user, $device, sha1($params['zkOpenid']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }

        } catch (Exception $e) {
            Log::error('cloudFI', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user = null, callable $cb = null)
    {
        $fans = empty($user) ? Session::fansInfo() : $user->profile();

        $data = [
            'channel' => $this->channel,
            'scene' => $this->scene,
            'areaCode' => intval($this->area),
            'msg' => "",
            'openid' => $fans['openid'],
            'nickname' => $fans['nickname'],
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'timestamp' => TIMESTAMP,
        ];

        $data['sign'] = md5($data['openid'].$data['timestamp'].$this->key);

        $result = HttpUtil::post(self::API_URL, $data);
        if ($cb) {
            $cb($data, $result);
        }
    }
}