<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use zovye\model\deviceModelObj;

class TKPromoting
{
    const REDIRECT_URL = 'https://cloud.tk.cn/tkproperty/nprd/S2023062001/?fromId=77973&channelCode=999999990004&cusType=3&device_id={device_uid}&extra={user_uid}';

    const DebugApiUrl = 'https://bag-gateway-test.ylkang.vip/bag-marketing';
    const ProdApiUrl = 'https://bag-gateway.ylkang.vip/bag-marketing';

    const RESPONSE = '{"code": "SUCCESS"}';

    private $app_id;
    private $app_secret;

    public function __construct($app_id, $app_secret)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
    }

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
        $uid = Config::tk('config.account_uid');
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

    static function getApiUrl(): string
    {
        return DEBUG ? self::DebugApiUrl : self::ProdApiUrl;
    }

    public function sign($timestamp): string
    {
        return sha1($this->app_id.$this->app_secret.$timestamp);
    }

    public function setNotifyUrl(): array
    {
        return $this->post('/channel', [
            'notify_url' => Util::murl('tk'),
        ]);
    }

    public function reg(deviceModelObj $device): array
    {
        return $this->post('/device', [
            'device_no' => $device->getImei(),
            'name' => $device->getName(),
        ]);
    }

    public static function deviceReg(deviceModelObj $device): bool
    {
        $config = Config::tk('config', []);
        if (empty($config) || empty($config['id']) || empty($config['secret'])) {
            Log::error('tk', [
                'reg device' => $device->profile(),
                'error' => '配置不正确！',
            ]);

            return false;
        }

        $res = (new TKPromoting($config['id'], $config['secret']))->reg($device);

        if (is_error($res)) {
            Log::error('tk', [
                'reg device' => $device->profile(),
                'error' => $res,
            ]);

            return false;
        }

        return true;
    }

    public static function confirmOrder(deviceModelObj $device, string $tk_order_no): bool
    {
        $config = Config::tk('config', []);
        if (empty($config) || empty($config['id']) || empty($config['secret'])) {
            Log::error('tk', [
                'confirm order' => $tk_order_no,
                'error' => '配置不正确！',
            ]);

            return false;
        }

        $res = (new TKPromoting($config['id'], $config['secret']))->confirm($device, $tk_order_no);

        if (is_error($res)) {
            Log::error('tk', [
                'confirm' => $tk_order_no,
                'error' => $res,
            ]);

            return false;
        }

        return true;
    }

    public function confirm(deviceModelObj $device, string $order_no): array
    {
        return $this->post('/order-confirm', [
            'device_no' => $device->getImei(),
            'order_no' => $order_no,
        ]);
    }

    public function post($path, $data): array
    {
        $header = [
            "x-app-id: ".$this->app_id,
            "x-timestamp: ".TIMESTAMP,
            "x-token: ".$this->sign(TIMESTAMP),
        ];

        $res = Util::post(self::getApiUrl().$path, $data, true, 3, [
            CURLOPT_HTTPHEADER => $header,
        ]);

        Log::debug('tk', [
            'path' => $path,
            'header' => $header,
            'request' => $data,
            'response' => $res,
        ]);

        return $res;
    }
}