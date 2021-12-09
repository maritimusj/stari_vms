<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class WxMCHPay
{
    const TRANSFER_URL = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
    const TRANSFER_INFO_URL = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/gettransferinfo';

    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 给指定的微信用户打款
     * @param $openid
     * @param $trade_no
     * @param $money
     * @param string $desc
     * @return mixed
     */
    public function transferTo($openid, $trade_no, $money, string $desc = ''): array
    {
        if ($money < MCH_PAY_MIN_MONEY) {
            return error(State::ERROR, '提现金额不能小于' . number_format(MCH_PAY_MIN_MONEY / 100, 2) . '元');
        }

        $data = array(
            'mch_appid' => $this->config['appid'],
            'mchid' => $this->config['mch_id'],
            'nonce_str' => Util::random(15),
            'partner_trade_no' => $trade_no,
            'openid' => $openid,
            'check_name' => 'NO_CHECK',
            'amount' => $money,
            'desc' => $desc,
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
        );

        $data['sign'] = $this->makeSign($data);

        $res = $this->curlApiUrl(self::TRANSFER_URL, $data);
        if (is_error($res)) {
            return $res;
        }

        return $this->parseResult($data, $res);
    }

    private function makeSign($params): string
    {
        unset($params['sign']);
        ksort($params);

        $str = '';
        foreach ($params as $key => $val) {
            if (empty($val)) {
                continue;
            }
            $str .= "$key=$val&";
        }

        return strtoupper(md5("{$str}key=" . $this->config['key']));
    }

    public function curlApiUrl($url, $data)
    {
        $data = is_array($data) ? $this->arrayToXML($data) : $data;

        $ch = curl_init();
        //超时时间
        //curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        //cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLCERT, $this->config['pem']['cert']);

        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEY, $this->config['pem']['key']);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $data = curl_exec($ch);

        if ($data) {
            curl_close($ch);
            return $data;
        }

        $error = curl_error($ch);
        curl_close($ch);

        return err($error);
    }

    private function arrayToXML($data): string
    {
        $content = '';
        foreach ($data as $key => $val) {
            $content .= "<$key>$val</$key>" . PHP_EOL;
        }

        return "<xml>$content</xml>";
    }

    /**
     * 解析微信返回的xml
     * @param $req
     * @param $input
     * @return mixed
     */
    public function parseResult($req, $input): array
    {
        if (empty($input)) {
            return error(State::ERROR, '通信失败，网络不通或者密钥文件有误？！');
        }

        $result = We7::xml2array($input);

        Log::debug('mchpay', array('req' => $req, 'resp' => $result));

        if (!is_array($result)) {
            return error(State::ERROR, '数据解析失败！');
        }

        if ((isset($result['return_code']) && $result['return_code'] == 'SUCCESS') &&
            (isset($result['result_code']) && $result['result_code'] == 'SUCCESS')) {

            return $result;
        }

        return error(State::ERROR, $result['err_code_des']);
    }

    /**
     * 转帐订单信息
     * @param $trade_no
     * @return mixed
     */
    public function transferInfo($trade_no): array
    {
        $data = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mch_id'],
            'nonce_str' => Util::random(15),
            'partner_trade_no' => $trade_no,
        );

        $data['sign'] = $this->makeSign($data);

        $res = $this->curlApiUrl(self::TRANSFER_INFO_URL, $data);
        if (is_error($res)) {
            return $res;
        }

        return $this->parseResult($data, $res);
    }

}
