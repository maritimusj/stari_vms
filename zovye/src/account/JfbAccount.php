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
use zovye\PlaceHolder;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class JfbAccount
{
    const REDIRECT_URL = 'http://wx.zhuna888.com/fans/?redirectUri={redirectUri}&channelId={channelId}&userId={userId}#/Jump';
    const CB_RESPONSE = 'ok';

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::JFB, Account::JFB_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user = null): array
    {
        $acc = Account::findOneFromType(Account::JFB);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->get('config', []);
        if (empty($config['url'])) {
            return [];
        }

        $api_url = strval($config['url']);

        $jfb_openid = $user->settings('customData.jfb.openid', '');
        if (!empty($config['auth']) && empty($jfb_openid)) {
            if (preg_match('/channelId=(\w*)/', $api_url, $result) > 0) {
                $channelId = $result[1];
                if ($channelId) {
                    $url = PlaceHolder::replace(self::REDIRECT_URL, [
                        'redirectUri' => urlencode(
                            Util::murl('jfb', ['op' => 'jfb_auth', 'device' => $device->getShadowId()])
                        ),
                        'channelId' => $channelId,
                        'userId' => $user,
                    ]);

                    $data = $acc->format();
                    $data['redirect_url'] = $url;

                    return [$data];
                }
            }
        }

        $fans = empty($user) ? Util::fansInfo() : $user->profile();
        $area = $device->getArea();

        $data = [
            'scene' => strval($config['scene']),
            'openId' => $fans['openid'],
            'facilityId' => $device->getImei(),
            'nickname' => $fans['nickname'],
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'headUrl' => $fans['headimgurl'],
            'ipAddress' => Util::getClientIp(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'countryName' => $fans['country'],
            'provinceName' => $fans['province'],
            'cityName' => $fans['city'],
            'requestType' => 1,
            'creativityType' => 0,
            'facilityCountry' => '',
            'facilityProvince' => $area[0] ?? '',
            'facilityCity' => $area[1] ?? '',
            'facilityDistrict' => $area[2] ?? '',
            'showTimes' => 0,
        ];

        if ($jfb_openid) {
            $data['zhunaOpenId'] = $jfb_openid;
        }

        $result = Util::post($api_url, $data);

        if (App::isAccountLogEnabled()) {
            $log = Account::createQueryLog($acc, $user, $device, $data, $result);
            if (empty($log)) {
                Log::error('jfb_query', [
                    'request' => $data,
                    'result' => $result,
                ]);
            }
        }

        $v = [];

        try {
            if (empty($result)) {
                throw new RuntimeException('返回数据为空！');
            }

            if (is_error($result)) {
                throw new RuntimeException($result['message']);
            }

            if (!$result['status'] || $result['errorCode'] != '0000') {
                throw new RuntimeException('失败，错误代码：'.$result['errorCode']);
            }

            $list = $result['result']['data'];

            if (isEmptyArray($list)) {
                throw new RuntimeException('没有数据！');
            }

            $data = $acc->format();
            foreach ($list as $item) {
                if ($item['qrPicUrl']) {
                    $data['title'] = $item['nickName'] ?: Account::JFB_NAME;
                    $data['img'] = $item['headImgUrl'] ?: Account::JFB_HEAD_IMG;
                    $data['qrcode'] = $item['qrPicUrl'];
                } elseif ($item['link']) {
                    $res = Util::createQrcodeFile("jfb.".sha1($item['link']), $item['link']);
                    if (is_error($res)) {
                        Log::error('jfb', [
                            'error' => 'fail to createQrcode file',
                            'result' => $res,
                        ]);
                        $data['redirect_url'] = $item['link'];
                    } else {
                        $data['qrcode'] = Util::toMedia($res);
                    }
                } else {
                    continue;
                }

                $v[] = $data;
            }

        } catch (Exception $e) {
            if (App::isAccountLogEnabled() && isset($log)) {
                $log->setExtraData('error_msg', $e->getMessage());
                $log->save();
            } else {
                Log::error('jfb', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $v;
    }

    public static function verifyData($params = []): array
    {
        unset($params);

        if (!App::isJfbEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOneFromType(Account::JFB);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        return ['account' => $acc];
    }

    public static function cb($params)
    {
        //op_type == 1 表示新关注
        if ($params['op_type'] == 1) {
            try {
                $res = self::verifyData($params);
                if (is_error($res)) {
                    throw new RuntimeException('发生错误：'.$res['message']);
                }

                /** @var userModelObj $user */
                $user = User::get($params['openid'], true);
                if (empty($user) || $user->isBanned()) {
                    throw new RuntimeException('用户已被禁用！');
                }

                /** @var accountModelObj $acc */
                $acc = $res['account'];

                if ($acc->getBonusType() == Account::BALANCE) {
                    $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['ad_code_no']}");
                    $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                    if (is_error($result)) {
                        throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                    }
                } else {
                    /** @var deviceModelObj $device */
                    $device = Device::get($params['device'], true);
                    if (empty($device)) {
                        throw new RuntimeException('找不对这个设备:'.$params['device']);
                    }

                    $order_uid = Order::makeUID($user, $device, sha1($params['ad_code_no']));
                    Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
                }

            } catch (Exception $e) {
                Log::error('jfb', [
                    'error' => $e->getMessage(),
                    'params' => $params,
                ]);
            }
        }
    }
}
