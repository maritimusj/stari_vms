<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace SQB;

use we7\ihttp;
use zovye\App;
use zovye\Log;
use zovye\State;
use function zovye\err;
use function zovye\error;
use function zovye\is_error;

class pay
{
    private $api = 'https://vsi-api.shouqianba.com';
    const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA5+MNqcjgw4bsSWhJfw2M
+gQB7P+pEiYOfvRmA6kt7Wisp0J3JbOtsLXGnErn5ZY2D8KkSAHtMYbeddphFZQJ
zUbiaDi75GUAG9XS3MfoKAhvNkK15VcCd8hFgNYCZdwEjZrvx6Zu1B7c29S64LQP
HceS0nyXF8DwMIVRcIWKy02cexgX0UmUPE0A2sJFoV19ogAHaBIhx5FkTy+eeBJE
bU03Do97q5G9IN1O3TssvbYBAzugz+yUPww2LadaKexhJGg+5+ufoDd0+V3oFL0/
ebkJvD0uiBzdE3/ci/tANpInHAUDIHoWZCKxhn60f3/3KiR8xuj2vASgEqphxT5O
fwIDAQAB
-----END PUBLIC KEY-----";
    private $config;

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    public function sign($params): string
    {
        $data = json_encode($params) . $this->config['key'];
        return md5($data);
    }

    public function checkSign($data, $sign): bool
    {
        return openssl_verify($data, base64_decode($sign), self::PUBLIC_KEY, OPENSSL_ALGO_SHA256) === 1;
    }

    public function requestApi($url, $params)
    {
        $res = ihttp::request($url, json_encode($params), [
            'Content-Type' => 'application/json',
            'Authorization' => "{$this->config['sn']} {$this->sign($params)}",
        ]);

         Log::debug('SQB', [
             'url' => $url,
             'request' => $params,
             'result' => $res,
         ]);

        if (is_error($res)) {
            return $res;
        }

        $content = json_decode($res['content'], true);
        if (empty($content)) {
            return error(State::FAIL, '请求失败！');
        }

        if ($content['result_code'] !== '200') {
            return err($content['error_message'] ?: '发生错误！');
        }

        return $content['biz_response'];
    }

    public function activate($device_id, $code)
    {
        $path = '/terminal/activate';
        return $this->requestApi("$this->api$path", [
            'app_id' => $this->config['app_id'],
            'code' => $code,
            'device_id' => $device_id,
        ]);
    }

    public function checkin($device_id)
    {
        $path = '/terminal/checkin';
        return $this->requestApi("$this->api$path", [
            'terminal_sn' => $this->config['sn'],
            'device_id' => $device_id,
        ]);
    }

    public function xAppPay($user_uid, $order_no, $amount, $device_uid, $desc = '', $notify_url = '')
    {
        $params = [];
        $params['terminal_sn'] = $this->config['sn'];       //收钱吧终端ID
        $params['client_sn'] = $order_no;                    //商户系统订单号,必须在商户系统内唯一；且长度不超过64字节
        $params['total_amount'] = "$amount";              //以分为单位,不超过10位纯数字字符串,超过1亿元的收款请使用银行转账

        if (App::isAliUser()) {
            $params['payway'] = '2';
        } else{
            $params['payway'] = '3';
        }

        $params['sub_payway'] = '4'; //小程序支付请传'4'
        $params['payer_uid'] = $user_uid;
        $params['subject'] = $desc;
        $params['operator'] = $device_uid;

        if (!empty($notify_url)) {
            $params['notify_url'] = $notify_url;
        }

        $path = '/upay/v2/precreate';
        return $this->requestApi("$this->api$path", $params);
    }

    public function wapApiPro($orderNO, $amount, $deviceUID, $desc = '', $notifyURL = '', $returnURL = ''): string
    {
        $params = [];
        $params['terminal_sn'] = $this->config['sn'];       //收钱吧终端ID
        $params['client_sn'] = $orderNO;                    //商户系统订单号,必须在商户系统内唯一；且长度不超过64字节
        $params['total_amount'] = "$amount";              //以分为单位,不超过10位纯数字字符串,超过1亿元的收款请使用银行转账
        $params['subject'] = $desc;                         //本次交易的概述
        $params['notify_url'] = $notifyURL;                 //支付回调的地址
        $params['operator'] = $deviceUID;                   //发起本次交易的操作员
        $params['return_url'] = $returnURL;                 //处理完请求后，当前页面自动跳转到商户网站里指定页面的http路径

        ksort($params);  //进行升序排序

        $getStr = function ($params) {
            $param_str = "";
            foreach ($params as $k => $v) {
                $param_str .= $k . '=' . $v . '&';
            }
            return $param_str;
        };

        $sign = strtoupper(md5($getStr($params) . 'key=' . $this->config['key']));

        $params['subject'] = urlencode($params['subject']);
        $params['notify_url'] = urlencode($params['notify_url']);
        $params['return_url'] = urlencode($params['return_url']);

        $paramsStr = $getStr($params) . 'sign=' . $sign;

        return "https://qr.shouqianba.com/gateway?" . $paramsStr;
    }

    public function refund($uid, $amount, $isSN = false, $serial = '')
    {
        $path = '/upay/v2/refund';
        $params = [
            'terminal_sn' => $this->config['sn'],
            'refund_amount' => "$amount",
            'refund_request_no' => empty($serial) ? 'R' . time() : $serial,
        ];
        if ($isSN) {
            $params['sn'] = $uid;
        } else {
            $params['client_sn'] = $uid;
        }

        return $this->requestApi("$this->api$path", $params);
    }

    public function query($orderNO)
    {
        $path = '/upay/v2/query';
        return $this->requestApi("$this->api$path", [
            'terminal_sn' => $this->config['sn'],
            'client_sn' => $orderNO,
        ]);
    }
}
