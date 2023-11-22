<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\payment;

use Psr\Http\Message\ResponseInterface;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use zovye\contract\IPay;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\PayUtil;
use zovye\util\Util;
use function zovye\err;
use function zovye\is_error;

abstract class WxPayV3 implements IPay
{
    abstract function getConfig():array;

    public function builder(): BuilderChainable
    {
        return PayUtil::getWxPayV3Builder($this->getConfig());
    }

    public function getCallbackUrl(): string
    {
        return PayUtil::getPaymentCallbackUrl($this->getConfig()['config_id']);
    }

    public function parseJSPayResponse(ResponseInterface $response)
    {
        $result = PayUtil::parseWxPayV3Response($response);

        if (is_error($result)) {
            return $result;
        }

        if (!empty($result['code'])) {
            return err($result['message'] ?? '请求失败！');
        }

        $config = $this->getConfig();

        $params = [
            'appId' => $config['appid'],
            'timeStamp' => (string)Formatter::timestamp(),
            'nonceStr' => Formatter::nonce(),
            'package' => "prepay_id={$result['prepay_id']}",
        ];

        $params += [
            'paySign' => Rsa::sign(
                Formatter::joinedByLineFeed(...array_values($params)),
                Rsa::from($config['pem']['key'])
            ),
            'signType' => 'RSA',
        ];

        return $params;
    }

    public function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        return PayUtil::getPayJs($device, $user);
    }

    public function refund(string $order_no, int $amount, bool $is_transaction_id = false)
    {
        $res = $this->query($order_no);
        if (is_error($res)) {
            return $res;
        }

        $data = [
            'json' => [
                'out_refund_no' => Util::random(32),
                'amount' => [
                    'refund' => $amount,
                    'total' => intval($res['total']),
                    'currency' => 'CNY',
                ],
            ],
        ];

        if ($is_transaction_id) {
            $data['json']['transaction_id'] = $order_no;
        } else {
            $data['json']['out_trade_no'] = $order_no;
        }

        $response = PayUtil::getWxPayV3Builder($this->getConfig())
            ->v3->refund->domestic->refunds
            ->post($data);

        $result = PayUtil::parseWxPayV3Response($response);

        if (!empty($result['code'])) {
            return err($result['message'] ?? '请求失败！');
        }

        return $result;
    }

    public function parseQueryResponse(ResponseInterface $response): array
    {
        $result = PayUtil::parseWxPayV3Response($response);

        if (!empty($result['code'])) {
            return err($result['message'] ?? '请求失败！');
        }

        return [
            'result' => 'success',
            'type' => $this->getName(),
            'merchant_no' => $result['sp_mchid'] ?? $result['mchid'],
            'orderNO' => $result['out_trade_no'],
            'transaction_id' => $result['transaction_id'],
            'total' => $result['amount'],
            'paytime' => $result['success_time'],
            'openid' => $result['payer']['sp_openid'] ?? $result['payer']['sub_openid'],
            'deviceUID' => $result['attach'],
        ];
    }

    public function decodeData(string $input): array
    {
        $signature = Request::header('WECHATPAY_SIGNATURE');
        $timestamp = Request::header('WECHATPAY_TIMESTAMP');
        $nonce = Request::header('WECHATPAY_NONCE');

        // 检查通知时间偏移量，允许5分钟之内的偏移
        if (abs(Formatter::timestamp() - (int)$timestamp) > 300) {
            return err('数据已超时！');
        }

        $config = $this->getConfig();

        $verified = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($timestamp, $nonce, $input),
            $signature,
            Rsa::from($config['pem']['cert'], Rsa::KEY_TYPE_PUBLIC)
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
        $inBodyResource = AesGcm::decrypt($ciphertext, $config['key'], $nonce, $aad);

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