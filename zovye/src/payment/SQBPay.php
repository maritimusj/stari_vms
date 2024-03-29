<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\payment;

use SQB\pay;
use zovye\contract\IPay;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\Util;
use zovye\util\PayUtil;
use function zovye\err;
use function zovye\error;
use function zovye\is_error;

class SQBPay implements IPay
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
        return \zovye\Pay::SQB;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    private function getSQB(): pay
    {
        return new pay($this->config);
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
        $notify_url = PayUtil::getPaymentCallbackUrl($this);

        $res = $this->getSQB()->qrPay($code, $order_no, $price, $device_uid, $body, $notify_url);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== 'PAY_SUCCESS') {
            if ($res['result_code'] == 'PAY_IN_PROGRESS') {
                return error(100, '正在支付中');
            }

            return err($res['error_message']);
        }

        return $res['data'] ?? err('不正确的返回数据！');
    }

    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $notify_url = PayUtil::getPaymentCallbackUrl($this);

        $res = $this->getSQB()->xAppPay($user_uid, $order_no, $price, $device_uid, $body, $notify_url);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== 'PRECREATE_SUCCESS') {
            return err($res['error_message']);
        }

        return is_array($res['data']['wap_pay_request']) ? $res['data']['wap_pay_request'] : [];
    }

    public function createJsPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $notify_url = PayUtil::getPaymentCallbackUrl($this);
        $pay_result_url = Util::murl('payresult', ['op' => 'SQB', 'orderNO' => $order_no, 'deviceid' => $device_uid]);
        $redirect_url = $this->getSQB()->wapApiPro($order_no, $price, $device_uid, $body, $notify_url, $pay_result_url);

        return [
            'redirect' => $redirect_url,
        ];
    }

    public function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        return PayUtil::getPayJs($device, $user);
    }

    public function close(string $order_no)
    {
        $res = $this->getSQB()->close($order_no);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] === 'CANCEL_SUCCESS') {
            return $res;
        }

        return err($res['error_message'] ?? '关闭订单失败');
    }

    public function refund(string $order_no, int $amount, bool $is_transaction_id = false)
    {
        $res = $this->getSQB()->refund($order_no, $amount, $is_transaction_id);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] === 'REFUND_SUCCESS') {
            return $res;
        }

        if ($res['result_code'] == 'REFUND_IN_PROGRESS') {
            return err('退款进行中');
        }

        return err($res['error_message'] ?: '退款失败');
    }

    public function query(string $order_no)
    {
        $res = $this->getSQB()->query($order_no);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] === 'SUCCESS') {
            $data = $res['data'];
            if ($data['order_status'] == 'PAID') {
                return [
                    'result' => 'success',
                    'type' => $this->getName(),
                    'pay_way' => $data['payway_name'],
                    'sn' => $this->config['sn'],
                    'orderNO' => $data['client_sn'],
                    'transaction_id' => $data['trade_no'],
                    'total' => $data['total_amount'],
                    'paytime' => $data['finish_time'],
                    'openid' => $data['payer_uid'],
                    'deviceUID' => $data['operator'],
                ];
            }

            if ($data['order_status'] == 'CREATED') {
                return error(100, '正在支付中');
            }

            return err('状态不正确！');
        }

        return err($res['error_message']);
    }

    public function decodeData(string $input): array
    {
        $data = json_decode($input, true);
        if ($data['status'] === 'SUCCESS' && $data['order_status'] === 'PAID') {
            return [
                'type' => 'SQB',
                'deviceUID' => $data['operator'],
                'orderNO' => $data['client_sn'],
                'total' => intval($data['total_amount']),
                'transaction_id' => $data['trade_no'],
                'raw' => $data,
            ];
        }

        return err('异常数据（未完成支付或者出错）！');
    }

    public function checkResult(array $data = []): bool
    {
        return $this->getSQB()->checkSign(Request::raw(), Request::header('HTTP_AUTHORIZATION'));
    }

    public function getResponse(bool $ok = true): string
    {
        return 'success';
    }
}