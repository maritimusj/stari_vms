<?php

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class MengMoAccount
{
    const API_URL = 'https://search-api.shenghuoq.com/dmp-search-api/v4/ad/noauth';
    const PUBLIC_KEY = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCQrNccDHWDJdXg2j13y8wNjf2de/ELKztcbLstpZfRm89GUHx9taCShli4bEVfxRDNiKvGVM20GbmKb/d2s9DSAPH5YlLtT0axZBdtTfENIUXzPZh9KhR2+owHX4O0sR41vqYjT7SGTyQhZKN13P/OcEAsLdq9r8ulycla0QMzyQIDAQAB';

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
                throw new RuntimeException('?????????????????????');
            }

            if (is_error($result)) {
                throw new RuntimeException($result['message']);
            }

            if (!$result['status'] || $result['errorCode'] != '0000') {
                throw new RuntimeException('???????????????'.$result['errorCode']);
            }

            $list = $result['result']['data'];
            if (isEmptyArray($list)) {
                throw new RuntimeException('???????????????');
            }

            foreach ($list as $item) {
                $data = $acc->format();

                if ($item['qrPicUrl']) {
                    $data['title'] = $item['nickName'] ?: Account::MENGMO_NAME;
                    $data['img'] = $item['headImgUrl'] ?: Account::MENGMO_HEAD_IMG;
                    $data['qrcode'] = $item['qrPicUrl'];
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
                Log::error('meng_mo', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $v;
    }

    public static function verifyData($params): array
    {
        if (!App::isMengMoEnabled()) {
            return err('???????????????');
        }

        $acc = Account::findOneFromType(Account::MENGMO);
        if (empty($acc)) {
            return err('???????????????????????????');
        }
        $keys = [
            'open_id',
            'qr_code_url',
            'subscribe_time',
            'facility_id',
            'ad_code_no',
            'wx_id',
            'op_type',
        ];

        $arr = [];
        foreach ($keys as $key) {
            $arr[] = "$key=$params[$key]";
        }

        $str = implode('&', $arr);

        if (!self::verifySignByMD5withRSA(self::PUBLIC_KEY, $str, $params['sign'])) {
            return err('?????????????????????');
        }

        return ['account' => $acc];
    }

    public static function verifySignByMD5withRSA($publicKey, $data, $sign): bool
    {
        $publicKey = chunk_split($publicKey, 64, "\n");
        $publicKey = "-----BEGIN PUBLIC KEY-----\n$publicKey-----END PUBLIC KEY-----\n";
        $sign = base64_decode($sign, true);

        return openssl_verify($data, $sign, $publicKey, OPENSSL_ALGO_MD5) === 1;
    }

    public static function cb($params = [])
    {
        //op_type == 1 ???????????????
        if ($params['op_type'] == 1) {
            try {
                $res = self::verifyData($params);
                if (is_error($res)) {
                    throw new RuntimeException('???????????????'.$res['message']);
                }

                /** @var userModelObj $user */
                $user = User::get($params['open_id'], true);
                if (empty($user) || $user->isBanned()) {
                    throw new RuntimeException('?????????????????????');
                }

                /** @var accountModelObj $acc */
                $acc = $res['account'];

                if ($acc->getBonusType() == Account::BALANCE) {
                    $serial = sha1("{$user->getId()}{$acc->getUid()}{$params['ad_code_no']}");
                    $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                    if (is_error($result)) {
                        throw new RuntimeException($result['message'] ?: '???????????????????????????');
                    }
                } else {
                    /** @var deviceModelObj $device */
                    $device = Device::get($params['facility_id'], true);
                    if (empty($device)) {
                        throw new RuntimeException('?????????????????????:'.$params['facility_id']);
                    }

                    $order_uid = Order::makeUID($user, $device, sha1($params['ad_code_no']));
                    Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
                }

            } catch (Exception $e) {
                Log::error('meng_mo', [
                    'error' => $e->getMessage(),
                    'params' => $params,
                ]);
            }
        }
    }
}