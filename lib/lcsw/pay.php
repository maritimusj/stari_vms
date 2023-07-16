<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace lcsw;

use we7\ihttp;
use zovye\LocationUtil;
use zovye\Session;
use function zovye\err;
use function zovye\error;
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

        if (Session::isAliUser()) {
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

    public function requestApi($url, $params)
    {
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

        if ($content['return_code'] !== '01') {
            return err($content['return_msg']);
        }

        return $content;
    }

    public function qrpay($params = [])
    {
        $path = '/pay/100/barcodepay';
        $data = [
            'pay_ver' => '100',
            'pay_type' => '000',
            'service_id' => '010',
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'terminal_trace' => $params['orderNO'],
            'terminal_time' => date('YmdHis'),
            'auth_no' => $params['code'],
            'total_fee' => "{$params['price']}",
        ];

        $data['key_sign'] = $this->sign($data);

        if (!empty($params['notify_url'])) {
            $data['notify_url'] = $params['notify_url'];
        }

        $data['terminal_ip'] = LocationUtil::getClientIp();

        $data['attach'] = $params['deviceUID'];
        $data['order_body'] = $params['body'];

        $res = $this->requestApi("$this->api$path", $data);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            if ($res['result_code'] == '03') {
                return  error(100, '正在支付中');
            }
            return err($res['return_msg'] ?? '订单付款失败！');
        }

        return $res;
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
        $data['terminal_ip'] = LocationUtil::getClientIp();

        $res = $this->requestApi("$this->api$path", $data);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            return err('支付失败！');
        }

        return $res;
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
        $data['terminal_ip'] = LocationUtil::getClientIp();

        $res = $this->requestApi("$this->api$path", $data);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            return err('创建支付失败！');
        }

        return $res;
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
            'terminal_trace' => 'Q'.microtime(true),
            'terminal_time' => date('YmdHis'),
            'out_trade_no' => $uid,
        ];

        $params['key_sign'] = $this->sign($params);

        $res = $this->requestApi("$this->api$path", $params);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            if ($res['result_code'] == '03') {
                return error(100, '正在支付中');
            }
            return err('查询订单失败！');
        }

        return $res;
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
            'terminal_trace' => empty($serial) ? 'R'.time() : $serial,
            'terminal_time' => date('YmdHis'),
            'refund_fee' => $total_fee,
            'out_trade_no' => $out_trade_no,
        ];

        $params['key_sign'] = $this->sign($params);

        $res = $this->requestApi("$this->api$path", $params);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            return err('退款失败！');
        }

        return $res;
    }
}