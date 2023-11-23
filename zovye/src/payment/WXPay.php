<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\payment;

use wx\pay;
use zovye\contract\IPay;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\PayUtil;
use zovye\We7;
use function zovye\is_error;

class WXPay implements IPay
{
    private $config;

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return \zovye\Pay::WX;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    private function getWx(): pay
    {
        return new pay([
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mch_id'],
            'key' => $this->config['key'],
            'pem' => $this->config['pem'],
        ]);
    }

    /**
     * 付款码支付
     */
    public function createQRCodePay(
        string $code,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ) {
        $params = [
            'device_info' => $device_uid,
            'out_trade_no' => $order_no,
            'auth_code' => $code,
            'body' => $body,
            'total_fee' => $price,
        ];

        return $this->getWx()->buildQrcodePay($params);
    }

    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        //小程序支付，使用小程序appid
        $this->config['appid'] = $this->config['wxappid'];

        return $this->createJsPay($user_uid, $device_uid, $order_no, $price, $body);
    }

    /**
     * 创建一个支付订单，并返回支付数据给前端js
     */
    public function createJsPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $wx = $this->getWx();

        $params = [
            'device_info' => $device_uid,
            'out_trade_no' => $order_no,
            'trade_type' => 'JSAPI',
            'openid' => $user_uid,
            'body' => $body,
            'total_fee' => $price,
            'notify_url' => PayUtil::getPaymentCallbackUrl($this->config['config_id']),
        ];

        $res = $wx->buildUnifiedOrder($params);

        if (is_error($res)) {
            return $res;
        }

        $data = [
            'appId' => $res['appid'],
            'timeStamp' => time().'',
            'nonceStr' => $res['nonce_str'],
            'package' => 'prepay_id='.$res['prepay_id'],
            'signType' => 'MD5',
        ];

        $data['paySign'] = $wx->buildSign($data);

        return $data;
    }

    /**
     * 获取页面支付时需要调用的js代码
     */
    public function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        return PayUtil::getPayJs($device, $user);
    }

    public function query(string $order_no)
    {
        $res = $this->getWx()->queryOrder($order_no);

        if (is_error($res)) {
            return $res;
        }

        return [
            'result' => 'success',
            'type' => $this->getName(),
            'merchant_no' => $this->config['mch_id'],
            'orderNO' => $res['out_trade_no'],
            'transaction_id' => $res['transaction_id'],
            'total' => $res['total_fee'],
            'paytime' => $res['time_end'],
            'openid' => $res['openid'],
            'deviceUID' => $res['device_info'],
        ];
    }

    public function close(string $order_no)
    {
        return $this->getWx()->close($order_no);
    }

    public function refund(string $order_no, int $amount, bool $is_transaction_id = false)
    {
        $res = $this->query($order_no);

        if (is_error($res)) {
            return $res;
        }

        return $this->getWx()->refund($order_no, intval($res['total']), $amount, $is_transaction_id);
    }

    public function decodeData(string $input): array
    {
        $data = We7::xml2array($input);

        return [
            'type' => 'wx',
            'deviceUID' => $data['device_info'],
            'orderNO' => $data['out_trade_no'],
            'total' => $data['total_fee'],
            'transaction_id' => $data['transaction_id'],
            'raw' => $data,
        ];
    }

    /**
     * 检验支付回调数据
     */
    public function checkResult(array $data = []): bool
    {
        if ($data['return_code'] != 'SUCCESS' || $data['result_code'] != 'SUCCESS') {
            return false;
        }

        return $this->getWx()->buildSign($data) === $data['sign'];
    }

    public function getResponse(bool $ok = true): string
    {
        $result = array(
            'return_code' => $ok ? 'SUCCESS' : 'FAIL',
            'return_msg' => $ok ? 'OK' : '',
        );

        return We7::array2xml($result);
    }
}
