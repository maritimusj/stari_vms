<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\payment;

use lcsw\pay;
use zovye\contract\IPay;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Session;
use zovye\util\PayUtil;
use function zovye\err;
use function zovye\is_error;

class LCSWPay implements IPay
{
    private $config;

    const RESPONSE_OK = '{"return_code":"01","return_msg":"SUCCESS"}';

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return \zovye\Pay::LCSW;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    private function getLCSW(): pay
    {
        return new pay([
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'access_token' => $this->config['access_token'],
        ]);
    }

    protected function createPay(
        callable $fn,
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $lcsw = $this->getLCSW();

        $params = [
            'userUID' => $user_uid,
            'deviceUID' => $device_uid,
            'orderNO' => $order_no,
            'price' => $price,
            'body' => $body,
            'notify_url' => PayUtil::getPaymentCallbackUrl($this->config['config_id']),
        ];

        $res = $fn($lcsw, $params);

        Log::debug('lcsw_xapppay', [
            'params' => $params,
            'res' => $res,
        ]);

        if (is_error($res)) {
            return $res;
        }

        if (Session::isAliUser()) {
            return [
                'orderNO' => $order_no,
                'tradeNO' => $res['ali_trade_no'],
                'tradeNo' => $res['ali_trade_no'],
            ];
        }

        return [
            'appId' => $res['appId'],
            'timeStamp' => $res['timeStamp'],
            'nonceStr' => $res['nonceStr'],
            'package' => $res['package_str'],
            'signType' => $res['signType'],
            'paySign' => $res['paySign'],
        ];
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
        $lcsw = $this->getLCSW();

        $params = [
            'code' => $code,
            'deviceUID' => $device_uid,
            'orderNO' => $order_no,
            'price' => $price,
            'body' => $body,
            'notify_url' => PayUtil::getPaymentCallbackUrl($this->config['config_id']),
        ];

        return $lcsw->qrpay($params);
    }

    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        return $this->createPay(function ($lcsw, $params) {
            return $lcsw->xAppPay($params);
        }, $user_uid, $device_uid, $order_no, $price, $body);
    }

    public function createJsPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = '',
        array $goodsDetail = []
    ): array {
        return $this->createPay(function ($lcsw, $params) use ($goodsDetail) {
            if ($goodsDetail) {
                $params['goods_detail'] = json_encode($goodsDetail);
            }

            return $lcsw->Jspay($params);
        }, $user_uid, $device_uid, $order_no, $price, $body);
    }

    public function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        return PayUtil::getPayJs($device, $user);
    }

    public function close(string $order_no)
    {
        $lcsw = $this->getLCSW();

        $res = $lcsw->close($order_no);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            return err($res['return_msg']);
        }

        return $res;
    }

    public function refund(string $order_no, int $amount, bool $is_transaction_id = false)
    {
        $res = $this->query($order_no);
        if (is_error($res)) {
            return $res;
        }

        if ($amount < 1 || $amount > $res['total']) {
            return err('退款金额不正确！');
        }

        $lcsw = $this->getLCSW();

        $res = $lcsw->doRefund($res['transaction_id'], $amount, $res['pay_type']);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            return err($res['return_msg']);
        }

        return $res;
    }

    public function query(string $order_no)
    {
        $lcsw = $this->getLCSW();
        $res = $lcsw->queryOrder($order_no);
        if (is_error($res)) {
            return $res;
        }

        return [
            'result' => 'success',
            'type' => $this->getName(),
            'pay_type' => $res['pay_type'],
            'merchant_no' => $this->config['merchant_no'],
            'orderNO' => $res['pay_trace'],
            'transaction_id' => $res['channel_trade_no'],
            'total' => $res['total_fee'],
            'paytime' => $res['end_time'],
            'openid' => $res['user_id'],
            'deviceUID' => $res['attach'],
        ];
    }

    public function decodeData(string $input): array
    {
        $data = json_decode($input, true);
        if (empty($data)) {
            return err('数据为空！');
        }

        if ($data['result_code'] !== '01') {
            return err($data['return_msg']);
        }

        return [
            'type' => 'lcsw',
            'deviceUID' => $data['attach'],
            'orderNO' => $data['terminal_trace'],
            'total' => intval($data['total_fee']),
            'transaction_id' => $data['channel_trade_no'],
            'raw' => $data,
        ];
    }

    public function checkResult(array $data = []): bool
    {
        $lcsw = $this->getLCSW();

        $sign = $lcsw->sign([
            'return_code' => $data['return_code'],
            'return_msg' => $data['return_msg'],
            'result_code' => $data['result_code'],
            'pay_type' => $data['pay_type'],
            'user_id' => $data['user_id'],
            'merchant_name' => $data['merchant_name'],
            'merchant_no' => $data['merchant_no'],
            'terminal_id' => $data['terminal_id'],
            'terminal_trace' => $data['terminal_trace'],
            'terminal_time' => $data['terminal_time'],
            'total_fee' => $data['total_fee'],
            'end_time' => $data['end_time'],
            'out_trade_no' => $data['out_trade_no'],
            'channel_trade_no' => $data['channel_trade_no'],
            'attach' => $data['attach'],
        ]);

        return $sign === $data['key_sign'];
    }

    public function getResponse(bool $ok = true): string
    {
        return self::RESPONSE_OK;
    }
}
