<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\userModelObj;
use zovye\model\deviceModelObj;

class JfbAccount
{
    const CB_RESPONSE = '{"result_code":0,"result_message":"成功"}';

    public static function getUid(): string
    {
        return Account::makeSpecialAccountUID(Account::JFB, Account::JFB_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user = null): array
    {
        $v = [];

        $acc = Account::findOne(['state' => Account::JFB]);
        if ($acc) {
            $config = $acc->get('config', []);
            if (empty($config['url'])) {
                return err('没有配置api url');
            }

            $fans = empty($user) ? Util::fansInfo() : $user->profile();

            $data = [
                'appNo' => '',
                'scene' => strval($config['scene']),
                'openId' => $fans['openid'],
                'facilityId' => $device->getImei(),
                'nickname' => $fans['nickname'],
                'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
                'headUrl' => $fans['headimgurl'],
                'ipAddress' => CLIENT_IP,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'countryName' => $fans['country'],
                'provinceName' => $fans['province'],
                'cityName' => $fans['city'],
                'requestType' => 1,
                'creativityType' => 0,
                'facilityCountry' => $fans['country'],
                'facilityProvince' => $fans['province'],
                'facilityCity' => $fans['city'],
                'facilityDistrict' => '',
                'showTimes' => 0,
                'redirect' => Util::murl('order', ['op' => 'feedback', 'device_imei' => $device->getImei(), 'device_name' => $device->getName()]),
                'replyMsg' => '出货中，请稍等！<a href="' . Util::murl('order', [
                    'op' => 'feedback',
                    'device_imei' => $device->getImei(),
                    'device_name' => $device->getName(),
                ]) . '">如未出货请点我！</a>',
            ];

            $result = Util::post(strval($config['url']), $data);

            if (App::isAccountLogEanbled()) {
                $log = Account::createQueryLog($acc, $user, $device, $data, $result);
                if (empty($log)) {
                    Util::logToFile('jfb_query', [
                        'request' => $data,
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

                if (!$result['status'] || $result['errorCode'] != '0000') {
                    throw new RuntimeException('失败，错误代码：' . $result['errorCode']);
                }

                $data = $acc->format();
                $x = $result['result']['data'][0];
                if (empty($x)) {
                    throw new RuntimeException('没有数据！');
                }

                $data['title'] = $x['nickName'];
                $data['img'] = $x['headImgUrl'];
                $data['qrcode'] = $x['qrPicUrl'];

                $v[] = $data;

                if (App::isAccountLogEanbled() && isset($log)) {
                    $log->setExtraData('account', $data);
                    $log->save();
                }
            } catch (Exception $e) {
                if (App::isAccountLogEanbled() && isset($log)) {
                    $log->setExtraData('error_msg', $e->getMessage());
                    $log->save();
                } else {
                    Util::logToFile('jfb', [
                        'error' => $e->getMessage()
                    ]);
                }
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

        $acc = Account::findOne(['state' => Account::JFB]);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        return ['account' => $acc];
    }

    public static function cb($params)
    {
        if ($params['op_type'] == 1) {
            try {
                $res = self::verifyData($params);
                if (is_error($res)) {
                    throw new RuntimeException('发生错误：' . $res['message']);
                }

                /** @var userModelObj $user */
                $user = User::get($params['openid'], true);
                if (empty($user) || $user->isBanned()) {
                    throw new RuntimeException('用户已被禁用！');
                }

                /** @var deviceModelObj $device */
                $device = Device::get($params['device'], true);
                if (empty($device)) {
                    throw new RuntimeException('找不对这个设备:' . $params['device']);
                }

                $order_uid = substr("U{$user->getId()}D{$device->getId()}{$params['sign']}" . Util::random(32), 0, MAX_ORDER_NO_LEN);

                $acc = $res['account'];

                Account::createSpecialAccountOrder($acc, $user, $device, $order_uid, $params);

            } catch (Exception $e) {
                Util::logToFile('jfb', [
                    'error' => $e->getMessage(),
                    'params' => $params,
                ]);
            }
        }
    }
}
