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
use function zovye\err;
use function zovye\is_error;

class KingFansAccount implements IAccountProvider
{
    const API_URL = 'https://api.wxyes.cn/pool';

    private $bid;
    private $key;

    /**
     * KingFansAccount constructor.
     * @param $bid
     * @param $key
     */
    public function __construct($bid, $key)
    {
        $this->bid = $bid;
        $this->key = $key;
    }

    public static function getUID(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::KINGFANS, Account::KINGFANS_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOneFromType(Account::KINGFANS);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->settings('config', []);
        if (empty($config['bid']) || empty($config['key'])) {
            return [];
        }

        $v = [];

        (new self($config['bid'], $config['key']))->fetchOne(
            $device,
            $user,
            function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Log::error('kingfans_query', [
                            'query' => $request,
                            'result' => $result,
                        ]);
                    }
                }

                try {
                    if (empty($result) || empty($result['list'])) {
                        throw new RuntimeException('返回数据为空！');
                    }

                    if (is_error($result)) {
                        throw new RuntimeException($result['message']);
                    }

                    if ($result['error']) {
                        throw new RuntimeException("请求失败，错误代码：{$result['error']}");
                    }

                    if (empty($result['list']['qrcode_url'])) {
                        throw new RuntimeException('二维码为空！');
                    }

                    $data = $acc->format();

                    $data['name'] = $result['list']['ghname'] ?: Account::KINGFANS_NAME;
                    $data['qrcode'] = $result['list']['qrcode_url'];
                    if ($result['list']['head_img']) {
                        $data['img'] = $result['list']['head_img'];
                    }

                    $v[] = $data;

                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Log::error('kingfans', [
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
        if (!App::isKingFansEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOneFromType(Account::KINGFANS);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        $config = $acc->settings('config', []);
        if (empty($config)) {
            return err('没有配置！');
        }

        if (md5($params['oid'].$params['uid'].$params['timestamp'].$config['key']) !== $params['sign']) {
            return err('签名错误！');
        }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        //出货流程
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：'.$res['message']);
            }

            list($device_shadow_uid, $user_openid) = explode(':', $params['param']);

            if (empty($device_shadow_uid) || empty($user_openid)) {
                throw new RuntimeException('发生错误：回传参数为格式不正确！');
            }

            /** @var userModelObj $user */
            $user = User::get($user_openid, true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['oid']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findOne(['shadow_id' => $device_shadow_uid]);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备:'.$device_shadow_uid);
                }

                $order_uid = Order::makeUID($user, $device, sha1($params['oid']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }

        } catch (Exception $e) {
            Log::error('kingfans', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }

    public function fetchOne(deviceModelObj $device, userModelObj $user = null, callable $cb = null)
    {
        $fans = empty($user) ? Session::fansInfo() : $user->profile();

        $data = [
            'bid' => $this->bid,
            'uuid' => $fans['openid'],
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'nickname' => $fans['nickname'],
            'ip' => $user->getLastActiveIp(),
            'os' => 0,
            'param' => "{$device->getShadowId()}:{$user->getOpenid()}",
            't' => TIMESTAMP,
        ];

        $data['sign'] = md5($this->key.$data['uuid'].$data['t']);

        $result = HttpUtil::get(self::API_URL.'?'.http_build_query($data));
        if (!empty($result) && is_string($result)) {
            $result = json_decode($result, true);
        }
        if ($cb) {
            $cb($data, $result);
        }
    }
}