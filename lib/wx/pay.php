<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace wx;

use we7\ihttp;
use zovye\Util;
use zovye\We7;
use function zovye\error;
use function zovye\is_error;

class pay
{
    private $config;

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    public function refund($no, $total, $refund_val = 0, $is_transaction_id = false)
    {
        if (empty($refund_val)) {
            $refund_val = $total;
        }

        $out_refund_no = Util::random(32);
        $data = [
            'total_fee' => $total,
            'refund_fee' => $refund_val,
            'out_refund_no' => $out_refund_no,
        ];
        if ($is_transaction_id) {
            $data['transaction_id'] = $no;
        } else {
            $data['out_trade_no'] = $no;
        }
        return $this->doRefund($data);
    }

    /*
     * 请求微信接口并解析返回结果
     */
    public function requestApi($url, $params, $extra = array())
    {
        $xml = We7::array2xml($params);
        $response = ihttp::request($url, $xml, $extra);
        if (is_error($response)) {
            return $response;
        }
        return $this->parseResult($response['content']);
    }

    /*
     * 解析微信返回的xml
     */
    public function parseResult($input): array
    {
        //如果不是xml则直接返回内容
        if ('<xml>' != substr($input, 0, 5)) {
            return $input;
        }

        $result = We7::xml2array($input);
        if (!is_array($result)) {
            return error(-1, 'xml结构错误');
        }

        if (isset($result['return_code']) && $result['return_code'] != 'SUCCESS') {
            return error(-1, $result['return_msg'] ?: '接口返回失败！');
        }

        if (isset($result['result_code']) && $result['result_code'] != 'SUCCESS') {
            return error(-1, $result['err_code_des'] ?: '接口返回失败！');
        }

        if (isset($result['trade_state']) && $result['trade_state'] != 'SUCCESS') {
            return error(-1, $result['trade_state_desc'] ?: '接口返回失败！');
        }

        if (!empty($result['sign']) && $this->bulidsign($result) != $result['sign']) {
            return error(-1, '验证签名出错！');
        }

        return $result;
    }


    public function bulidSign($params): string
    {
        unset($params['sign']);
        ksort($params);

        $string = $this->array2url($params);
        $string = $string . "&key={$this->config['key']}";
        $string = md5($string);

        return strtoupper($string);
    }

    public function array2url($params): string
    {
        $str = '';
        $ignore = array('coupon_refund_fee', 'coupon_refund_count');
        foreach ($params as $key => $val) {
            if ((empty($val) || is_array($val)) && !in_array($key, $ignore)) {
                continue;
            }
            $str .= "$key=$val&";
        }
        return trim($str, '&');
    }

    /*
     * 转换短网址
     * */
    public function shortUrl($url)
    {
        $params = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mch_id'],
            'long_url' => $url,
            'nonce_str' => Util::random(32),
        );
        $params['sign'] = $this->bulidSign($params);
        $result = $this->requestApi('https://api.mch.weixin.qq.com/tools/shorturl', $params);
        if (is_error($result)) {
            return $result;
        }

        return $result['short_url'];
    }

    /*
     * 扫码模式一生成支付url
     * */
    public function bulidNativePayurl($product_id, $short_url = true)
    {
        $params = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mch_id'],
            'time_stamp' => TIMESTAMP,
            'nonce_str' => Util::random(32),
            'product_id' => $product_id,
        );
        $params['sign'] = $this->bulidSign($params);
        $url = 'weixin://wxpay/bizpayurl?' . $this->array2url($params);
        if ($short_url) {
            $url = $this->shortUrl($url);
        }

        return $url;
    }

    /*
     * 接口
     * */
    public function buildUnifiedOrder($params)
    {
        //检测必填参数
        if (empty($params['out_trade_no'])) {
            return error(-1, '缺少统一支付接口必填参数out_trade_no:商户订单号');
        }
        if (empty($params['body'])) {
            return error(-1, '缺少统一支付接口必填参数body:商品描述');
        }
        if (empty($params['total_fee'])) {
            return error(-1, '缺少统一支付接口必填参数total_fee:总金额');
        }
        if (empty($params['trade_type'])) {
            return error(-1, '缺少统一支付接口必填参数trade_type:交易类型');
        }

        //关联参数
        if ('JSAPI' == $params['trade_type'] && empty($params['openid'])) {
            return error(-1, '统一支付接口中，缺少必填参数openid！交易类型为JSAPI时，openid为必填参数！');
        }
        if ('NATIVE' == $params['trade_type'] && empty($params['product_id'])) {
            return error(-1, '统一支付接口中，缺少必填参数product_id！交易类型为NATIVE时，product_id为必填参数！');
        }

        if (empty($params['notify_url'])) {
            $params['notify_url'] = $this->config['notify_url'];
        }

        $params['appid'] = $this->config['appid'];
        $params['mch_id'] = $this->config['mch_id'];
        $params['spbill_create_ip'] = CLIENT_IP;
        $params['nonce_str'] = Util::random(32);
        $params['sign'] = $this->bulidSign($params);

        return $this->requestApi('https://api.mch.weixin.qq.com/pay/unifiedorder', $params);
    }

    /*
     * 查询订单
     * */
    public function queryOrder($no, $is_transaction_id = false)
    {
        $params = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mch_id'],
            'nonce_str' => Util::random(32),
        );
        if ($is_transaction_id) {
            $params['transaction_id'] = $no;
        } else {
            $params['out_trade_no'] = $no;
        }

        $params['sign'] = $this->bulidSign($params);

        return $this->requestApi('https://api.mch.weixin.qq.com/pay/orderquery', $params);
    }

    /*
     * 申请退款
     * $params 退款参数
     * */
    public function doRefund($params)
    {
        $params['appid'] = $this->config['appid'];
        $params['mch_id'] = $this->config['mch_id'];
        $params['nonce_str'] = Util::random(32);

        $params['sign'] = $this->bulidSign($params);
        $pem = $this->config['pem'];

        return $this->requestApi('https://api.mch.weixin.qq.com/secapi/pay/refund', $params, [
            CURLOPT_SSLCERTTYPE => 'PEM',
            CURLOPT_SSLCERT => $pem['cert'],
            CURLOPT_SSLKEYTYPE => 'PEM',
            CURLOPT_SSLKEY => $pem['key'],
        ]);
    }
}