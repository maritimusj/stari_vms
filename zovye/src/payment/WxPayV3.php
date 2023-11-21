<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\payment;

use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use zovye\contract\IPay;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Pay;
use zovye\Request;
use zovye\util\Util;
use zovye\util\PayUtil;
use function zovye\err;
use function zovye\is_error;

class WxPayV3 implements IPay
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
        return Pay::WX_V3;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function createXAppPay(string $user_uid, string $device_uid, string $order_no, int $price, string $body = '')
    {
        //小程序支付，使用小程序appid
        $this->config['appid'] = $this->config['wxappid'];

        return $this->createJsPay($user_uid, $device_uid, $order_no, $price, $body);
    }

    public function createJsPay(string $user_uid, string $device_uid, string $order_no, int $price, string $body = '')
    {
        $data = [
            'sp_appid' => $this->config['appid'],
            'sp_mchid' => $this->config['mch_id'],
            'sub_mchid' => $this->config['sub_mch_id'],
            'description' => $body,
            'out_trade_no' => $order_no,
            'notify_url' => PayUtil::getPaymentCallbackUrl($this->config['config_id']),
            'amount' => [
                'total' => $price,
                'currency' => 'CNY',
            ],
            'payer' => [
                'sp_openid' => $user_uid,
            ],
            'attach' => $device_uid,
        ];

        $response = PayUtil::getWxPayV3Client($this->config)->post('/v3/pay/partner/transactions/jsapi', $data);

        Log::debug('v3', [
            'config' => $this->config,
            'data' => $data,
            'response' => $response,
        ]);

        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            return err($response['message'] ?? '请求失败！');
        }

        $params = [
            'appId' => $this->config['appid'],
            'timeStamp' => (string)Formatter::timestamp(),
            'nonceStr' => Formatter::nonce(),
            'package' => "prepay_id={$response['prepay_id']}",
        ];

        $params += [
            'paySign' => Rsa::sign(
                Formatter::joinedByLineFeed(...array_values($params)),
                Rsa::from($this->config['pem']['key'])
            ),
            'signType' => 'RSA',
        ];

        return $params;
    }

    public function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        return PayUtil::getPayJs($device, $user);
    }

    public function close(string $order_no)
    {
        $data = [
            'sp_mchid' => $this->config['mch_id'],
            'sub_mchid' => $this->config['sub_mch_id'],
            'order_no' => $order_no,
        ];

        $response = PayUtil::getWxPayV3Client($this->config)
            ->instance()
            ->v3->pay->partner->transactions->outTradeNo->_order_no_->close
            ->post($data);

        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            return err($response['message'] ?? '请求失败！');
        }

        return $response;
    }

    public function refund(string $order_no, int $amount, bool $is_transaction_id = false)
    {
        $res = $this->query($order_no);
        if (is_error($res)) {
            return $res;
        }

        $data = [
            'sub_mchid' => $this->config['sub_mch_id'],
            'out_refund_no' => Util::random(32),
            'amount' => [
                'refund' => $amount,
                'total' => intval($res['total']),
                'currency' => 'CNY',
            ],
        ];

        if ($is_transaction_id) {
            $data['transaction_id'] = $order_no;
        } else {
            $data['out_trade_no'] = $order_no;
        }

        $response = PayUtil::getWxPayV3Client($this->config)
            ->instance()
            ->v3->refund->domestic->refunds
            ->post($data);

        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            return err($response['message'] ?? '请求失败！');
        }

        return $response;
    }

    public function query(string $order_no)
    {
        $data = [
            'sp_mchid' => $this->config['mch_id'],
            'sub_mchid' => $this->config['sub_mch_id'],
            'order_no' => $order_no,
        ];

        $response = PayUtil::getWxPayV3Client($this->config)
            ->instance()
            ->v3->pay->partner->transactions->outTradeNo->_order_no_
            ->get($data);

        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            return err($response['message'] ?? '请求失败！');
        }

        return [
            'result' => 'success',
            'type' => $this->getName(),
            'merchant_no' => $this->config['mch_id'],
            'orderNO' => $response['out_trade_no'],
            'transaction_id' => $response['transaction_id'],
            'total' => $response['amount'],
            'paytime' => $response['success_time'],
            'openid' => $response['payer']['sp_openid'] ?? $response['payer']['sub_openid'],
            'deviceUID' => $response['attach'],
        ];
    }

    public function decodeData(string $input): array
    {
        $signature = Request::header('WECHATPAY_SIGNATURE');// 请根据实际情况获取
        $timestamp = Request::header('WECHATPAY_TIMESTAMP');// 请根据实际情况获取
        $nonce = Request::header('WECHATPAY_NONCE');// 请根据实际情况获取

        // 检查通知时间偏移量，允许5分钟之内的偏移
        if (300 < abs(Formatter::timestamp() - (int)$timestamp)) {
            return err('数据已超时！');
        }

        $verified = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($timestamp, $nonce, $input),
            $signature,
            Rsa::from($this->config['pem']['cert'], Rsa::KEY_TYPE_PUBLIC)
        );

        if (!$verified) {
            return err('数据检验失败！');
        }

        // 转换通知的JSON文本消息为PHP Array数组
        $inBodyArray = (array)json_decode($input, true);

        if (!empty($inBodyArray['code'])) {
            return err($inBodyArray['message'] ?? '支付失败！');
        }

        // 使用PHP7的数据解构语法，从Array中解构并赋值变量
        [
            'resource' => [
                'ciphertext' => $ciphertext,
                'nonce' => $nonce,
                'associated_data' => $aad,
            ],
        ] = $inBodyArray;

        // 加密文本消息解密, $this->config['key'] APIv3密钥
        $inBodyResource = AesGcm::decrypt($ciphertext, $this->config['key'], $nonce, $aad);

        // 把解密后的文本转换为PHP Array数组
        $data = (array)json_decode($inBodyResource, true);
        if ($data['trade_state'] != 'SUCCESS') {
            return err('支付失败！');
        }

        return [
            'type' => $this->getName(),
            'deviceUID' => $data['attach'],
            'orderNO' => $data['out_trade_no'],
            'total' => $data['amount'],
            'transaction_id' => $data['transaction_id'],
            'raw' => $data,
        ];
    }

    public function checkResult(array $data = [])
    {
        if ($data['trade_state'] != 'SUCCESS') {
            return err('支付失败！');
        }

        return true;
    }

    public function getResponse(bool $ok = true): array
    {
        if ($ok) {
            return [
                'code' => 'SUCCESS',
                'message' => '成功',
            ];
        }

        return [
            'code' => 'FAIL',
            'message' => '失败',
        ];
    }
}