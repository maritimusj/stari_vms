<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace lcsw;

use we7\ihttp;
use zovye\App;

use zovye\Util;
use function zovye\err;
use function zovye\is_error;

class pay
{
    const WX_PAY = '010';
    const ALI_PAY = '020';

    private $api = "https://pay.lcsw.cn/lcsw";

    private $config;
    private $pay_type;

    public function __construct($config = [])
    {
        $this->config = $config;

        if (App::isAliUser()) {
            $this->pay_type = self::ALI_PAY;
        } else {
            $this->pay_type = self::WX_PAY;
        }
    }

    public function sign($params, $sort = false): string
    {
        if ($sort) {
            ksort($params);
        }

        $arr = [];
        foreach ($params as $key => $val) {  
            if ($key == 'key_sign') {
                continue;
            }
            $arr[] = "$key=$val";
        }

        $arr[] = "access_token={$this->config['access_token']}";
        return md5(implode('&', $arr));
    }

    public function requestApi($url, $params) {
        $res = ihttp::request($url, json_encode($params), ['Content-Type' => 'application/json']);
        if (is_error($res)) {
            return $res;
        }

        $content = json_decode($res['content'], true);
        if (empty($content)) {
            return err('请求失败！');
        }
        
        if ($content['success'] === 'error') {
            return err($content['msg']);
        }

        if ($content['return_code'] !== '01' || $content['result_code'] !== '01') {
            return err($content['return_msg']);
        }

        return $content;
    }

    public function qrpay($params = [])
    {
        $path = '/pay/110/qrpay';
        $data = [
            'pay_ver' => '110',
            'pay_type' => '000',//$this->pay_type,
            'service_id' => '016',
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'terminal_trace' => $params['orderNO'],
            'terminal_time' => date('YmdHis'),
            'total_fee' => $params['price'],
        ];

        if (!empty($params['notify_url'])) {
            $data['notify_url'] = $params['notify_url'];
        }

        $data['attach'] = $params['deviceUID'];
        $data['order_body'] = $params['body'];

        $data['key_sign'] = $this->sign($data, true);
        $data['terminal_ip'] = Util::getClientIp();

        return $this->requestApi("$this->api$path", $data);
    }

    public function xAppPay($params = [])
    {
        $path = '/pay/100/minipay';
        $data = [
            'pay_ver' => '100',
            'pay_type' => $this->pay_type,
            'service_id' => '015',
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'terminal_trace' => $params['orderNO'],
            'terminal_time' => date('YmdHis'),
            'total_fee' => $params['price'],
        ];

        $data['key_sign'] = $this->sign($data);

        if (!empty($params['notify_url'])) {
            $data['notify_url'] = $params['notify_url'];
        }

        $data['open_id'] = $params['userUID'];
        $data['attach'] = $params['deviceUID'];
        $data['order_body'] = $params['body'];
        $data['terminal_ip'] = Util::getClientIp();
                
        return $this->requestApi("$this->api$path", $data);
    }


    public function Jspay($params = [])
    {
        $path = '/pay/100/jspay';
        $data = [
            'pay_ver' => '100',
            'pay_type' => $this->pay_type,
            'service_id' => '012',
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'terminal_trace' => $params['orderNO'],
            'terminal_time' => date('YmdHis'),
            'total_fee' => $params['price'],
        ];

        $data['key_sign'] = $this->sign($data);

        if (!empty($params['notify_url'])) {
            $data['notify_url'] = $params['notify_url'];
        }

        $data['open_id'] = $params['userUID'];
        $data['attach'] = $params['deviceUID'];
        $data['order_body'] = $params['body'];
        $data['terminal_ip'] = Util::getClientIp();
       
        return $this->requestApi("$this->api$path", $data);
    }   

    public function queryOrder($uid)
    {
        $path = '/pay/100/query';
        $params = [
            'pay_ver' => '100',
            'pay_type' => '000',
            'service_id' => '020',
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'terminal_trace' => 'Q' . microtime(true),
            'terminal_time' => date('YmdHis'),
            'out_trade_no' => $uid,
        ];

        $params['key_sign'] = $this->sign($params);

        return $this->requestApi("$this->api$path", $params);
    }

    public function doRefund($out_trade_no, $total_fee, $pay_type, $serial = '')
    {
        $path = '/pay/100/refund';

        $params = [
            'pay_ver' => '100',
            'pay_type' => $pay_type,
            'service_id' => '030',
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'terminal_trace' => empty($serial) ? 'R' . time() : $serial,
            'terminal_time' => date('YmdHis'),
            'refund_fee' => $total_fee,
            'out_trade_no' => $out_trade_no,
        ];

        $params['key_sign'] = $this->sign($params);

        return $this->requestApi("$this->api$path", $params);
    }
}