<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class MengMoAccount
{
    const API_URL = 'https://search-api.shenghuoq.com/dmp-search-api/v4/ad/noauth';

    public static function getUid(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::MENGMO, Account::MENGMO_NAME);
    }

    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOneFromType(Account::MENGMO);
        if (empty($acc)) {
            return [];
        }

        $config = $acc->get('config', []);
        if (empty($config['app_no'])) {
            return [];
        }

        $fans = empty($user) ? Util::fansInfo() : $user->profile();
        $area = $device->getArea();

        $data = [
            'appNo' => strval($config['app_no']),
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
        ];

        $result = Util::post(self::API_URL, $data);

        if (App::isAccountLogEnabled()) {
            $log = Account::createQueryLog($acc, $user, $device, $data, $result);
            if (empty($log)) {
                Log::error('meng_mo_query', [
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
                throw new RuntimeException('错误代码：' . $result['errorCode']);
            }

            $item = current($result['result']['data']);
            if (isEmptyArray($item)) {
                throw new RuntimeException('没有数据！');
            }

            $data = $acc->format();
            if ($item['qrPicUrl']) {
                $data['title'] = $item['nickName'] ?: Account::JFB_NAME;
                $data['img'] = $item['headImgUrl'] ?: Account::JFB_HEAD_IMG;
                $data['qrcode'] = $item['qrPicUrl'];
            } else {
                throw new RuntimeException('没有二维码数据！');
            }

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
                Log::error('meng_mo', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $v;
    }

    public static function verifyData($params): array
    {
        if (!App::isMengMoEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOneFromType(Account::MENGMO);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        $sign = md5("open_id={$params['open_id']}&qr_code_url={$params['qr_code_url']}&subscribe_time={$params['subscribe_time']}");
        if ($sign !== $params['sign']) {
            return err('签名检验失败！');
        }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        //op_type == 1 表示新关注
        if ($params['op_type'] == 1) {
            try {
                $res = self::verifyData($params);
                if (is_error($res)) {
                    throw new RuntimeException('发生错误：' . $res['message']);
                }

                /** @var userModelObj $user */
                $user = User::get($params['open_id'], true);
                if (empty($user) || $user->isBanned()) {
                    throw new RuntimeException('用户已被禁用！');
                }

                /** @var deviceModelObj $device */
                $device = Device::get($params['facility_id'], true);
                if (empty($device)) {
                    throw new RuntimeException('找不对这个设备:' . $params['device']);
                }

                $acc = $res['account'];

                $order_uid = Order::makeUID($user, $device, $params['ad_code_no']);

                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);

            } catch (Exception $e) {
                Log::error('meng_mo', [
                    'error' => $e->getMessage(),
                    'params' => $params,
                ]);
            }
        }
    }
}