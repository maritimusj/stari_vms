<?php

namespace zovye;

class ChuanglanSmsApi
{
    const API_URL = 'https://intapi.253.com/send/json';

    const API_ACCOUNT = ''; // 创蓝API账号

    const API_PASSWORD = '';// 创蓝API密码

    const MOBILE_PREFIX = '001';
    
    const CONTENT_TPL = 'your verification code is [{code}]';

    public function send(string $mobile, string $code): array
    {

        if (empty(self::API_ACCOUNT) || empty(self::API_PASSWORD)) {
            return err('Invalid config of SMS api.');
        }

        $msg = PlaceHolder::replace(self::CONTENT_TPL, [
            'code' => $code,
        ]);

        $data = array(
            'account' => self::API_ACCOUNT,
            'password' => self::API_PASSWORD,
            'msg' => $msg,
            'mobile' => self::MOBILE_PREFIX . $mobile,
        );

        return Util::post(self::API_URL, $data);
    }

}