<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class TKPromoting
{
    const REDIRECT_URL = 'https://cloud.tk.cn/tkproperty/nprd/S2023062001/?fromId=77973&channelCode=999999990004&cusType=3&device_id={device_uid}&utm_source=FPDJK168708928ac9&extra={user_uid}';

    const EncryptKey = 'fe355d547d506890689f2889464f323a63666543001011061';

    const DebugApiUrl = 'http://tkoh-t.tk.cn/hopen/cusoptui/channel/order/confirmOrder';
    const ProdApiUrl = 'https://cloud.tk.cn/hopen/cusoptui/channel/order/confirmOrder';

    public static function getAd(): array
    {
        return [
            'id' => 0,
            'title' => '泰康保险',
            'data' => [
                'images' => [
                    MODULE_URL.'static/img/tk001.png',
                ],
                'link' => self::REDIRECT_URL,
            ],
        ];
    }

    public static function getAccount()
    {
        $uid = Config::tk('config.account');
        if (empty($uid)) {
            return err('没有配置公众号！');
        }

        $acc = Account::findOneFromUID($uid);
        if (empty($acc)) {
            return err('公众号配置不正确！');
        }

        if ($acc->isBanned()) {
            return err('公众号已禁用！');
        }

        return $acc;
    }

    public static function sign($event_time)
    {
        $config = Config::tk('config');
        if (isEmptyArray($config) || empty($config['id']) || empty($config['secret'])) {
            return err('配置不正确！');
        }

        $hash_val = md5($config['id'].$event_time);

        return "$hash_val.$event_time";
    }

    public static function confirm($proposalNo): array
    {
        if (empty($proposalNo)) {
            return err('用户没有签约信息！');
        }

        $data = [
            'requestId' => REQUEST_ID,
            'requestTime' => date('YmdHis'),
            'requestData' => self::encrypt([
                'orderType' => 1,
                'proposalNo' => $proposalNo,
            ]),
        ];

        $result = Util::post(DEBUG ? self::DebugApiUrl : self::ProdApiUrl, $data);

        Log::debug('tk', [
            'request' => $data,
            'response' => $result,
        ]);

        return $result;
    }

    static function pkcs7_pad($data, $block_size) {
        $padding = $block_size - (strlen($data) % $block_size);
        return $data . str_repeat(chr($padding), $padding);
    }
    
    static function pkcs7_unpad($data) {
        $padding = ord($data[strlen($data) - 1]);
        return substr($data, 0, -$padding);
    }

    public static function encrypt($data): string
    {
        $key = Config::tk('config.key');var_dump($key);

        $block_size = 16;
        $data = self::pkcs7_pad($data, $block_size);
        $iv = str_repeat(chr(0), $block_size);
        $encrypted = openssl_encrypt($data, 'AES-256-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        return base64_encode($encrypted);
    }

    public static function decrypt($data)
    {
        $key = Config::tk('config.key');

        $block_size = 16;
        $data = base64_decode($data);
        $iv = str_repeat(chr(0), $block_size);
        $decrypted = openssl_decrypt($data, 'AES-256-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        return self::pkcs7_unpad($decrypted);
    }
}