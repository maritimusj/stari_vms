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
use zovye\Device;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class YouFenAccount
{
    const REDIRECT_URL = 'https://dsp.of.youfen.cc/auth.html';
    const API_URL = 'https://dsp.api.youfen.cc/api/channel/ad/get';

    private $app_number;
    private $app_key;

    public function __construct($app_number, $app_key)
    {
        $this->app_number = $app_number;
        $this->app_key = $app_key;
    }

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::YOUFEN, Account::YOUFEN_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        /** @var accountModelObj $acc */
        $acc = Account::findOneFromType(Account::YOUFEN);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->settings('config', []);
        if (empty($config['app_number']) || empty($config['app_key'])) {
            return [];
        }

        $v = [];

        $yf_openid = $user->settings('customData.yf.openid', '');
        if ($yf_openid) {
            //请求API
            $youFen = new YouFenAccount($config['app_number'], $config['app_key']);
            $youFen->fetchOne(
                $device,
                $user,
                $acc,
                $yf_openid,
                function ($request, $result) use ($acc, $device, $user, &$v) {
                    if (App::isAccountLogEnabled()) {
                        $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                        if (empty($log)) {
                            Log::error('youfen_query', [
                                'query' => $request,
                                'result' => $result,
                            ]);
                        }
                    }

                    if (empty($result) || is_error($result) || !$result['success']) {
                        Log::error('youfen', [
                            'user' => $user->profile(),
                            'acc' => $acc->getName(),
                            'device' => $device->profile(),
                            'error' => $result,
                        ]);
                    } else {
                        $list = $result['result'];
                        if (!isEmptyArray($list)) {
                            $data = $acc->format();
                            foreach ($list as $item) {
                                if ($item && $item['qr_url']) {
                                    $data['title'] = $item['wx_nickname'] ?? Account::YOUFEN_NAME;
                                    $data['qrcode'] = $item['qr_url'];
                                    $v[] = $data;
                                }
                            }
                        }
                    }
                }
            );
        } else {
            $data = $acc->format();
            $params = [
                'wx_nickname' => $user->getNickname(),
                'wx_headimg' => $user->getAvatar(),
                're_url' => Util::murl('youfen', ['op' => 'yf_auth', 'device' => $device->getShadowId()]),
            ];

            $data['redirect_url'] = self::REDIRECT_URL.'?'.http_build_query($params);
            $v[] = $data;
        }

        return $v;

    }

    public function sign($data): string
    {
        ksort($data);
        $str = urldecode(http_build_query($data, '', '&amp;'));

        return md5($str);
    }

    public function fetchOne(
        deviceModelObj $device,
        userModelObj $user,
        accountModelObj $acc,
        string $yf_openid,
        callable $cb = null
    ) {
        $fans = $user->profile();
        $uid = App::uid(6);
        $data = [
            'app_number' => $this->app_number,
            'app_key' => $this->app_key,
            'ip_address' => $user->getLastActiveIp(),
            'user_id' => $user->getOpenid(),
            'wx_openid' => $yf_openid,
            'wx_nickname' => $fans['nickname'],
            'wx_headimg' => empty($fans['avatar']) ? $fans['headimgurl'] : $fans['avatar'],
            'reply_title' => $acc->getTitle() == Account::YOUFEN_NAME ? '点击免费领取' : $acc->getTitle(),
            'reply_description' => $acc->getDescription(),
            'followed_title' => $acc->settings('config.followed_title', '出货成功'),
            'followed_description' => $acc->settings('config.followed_description', '如果没有领取到商品，请重新扫描屏幕二维码！'),
            'notify_data' => "$uid:{$device->getShadowId()}:{$user->getOpenid()}",
        ];

        $data['sign'] = $this->sign($data);

        $result = Util::post(self::API_URL, $data);
        if ($cb) {
            $cb($data, $result);
        }
    }

    public static function verifyData($params): array
    {
        if (!App::isYouFenEnabled()) {
            return err('没有启用该功能！');
        }

        if (isEmptyArray($params)) {
            return err('请求数据为空！');
        }

        $acc = Account::findOneFromType(Account::YOUFEN);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        $config = $acc->settings('config', []);

        if (empty($config)) {
            return err('没有配置！');
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

            list($uid, $device_uid, $user_uid) = explode(':', $params['notify_data']);
            if ($uid !== App::uid(6) || empty($device_uid) || empty($user_uid)) {
                throw new RuntimeException('发生错误：异常数据！');
            }

            /** @var userModelObj $user */
            $user = User::get($user_uid, true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['request_id']}");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findOne(['shadow_id' => $device_uid]);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备:'.$params['params']);
                }

                $order_uid = Order::makeUID($user, $device, sha1($params['request_id']));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }

        } catch (Exception $e) {
            Log::error('youfen', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }
}