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
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\Util;
use zovye\util\WxPayUtil;
use function zovye\_W;
use function zovye\err;
use function zovye\is_error;

class WxPayV3 implements IPay
{
    private $config = [];

    public function getName(): string
    {
        return 'wx_v3';
    }

    public function setConfig(array $config = [])
    {
        $this->config = $config;
    }

    protected function getNotifyUrl(): string
    {
        $notify_url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        $notify_url .= 'payment/wx_v3.php';

        return $notify_url;
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
            'notify_url' => $this->getNotifyUrl(),
            'amount' => $price,
            'payer' => [
                'sp_openid' => $user_uid,
            ],
            'attach' => $device_uid,
        ];

        $response = WxPayUtil::getV3Client($this->config)->post('/v3/pay/partner/transactions/jsapi', $data);
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
        return WxPayUtil::getPayJs($device, $user);
    }

    public function close(string $order_no)
    {
        $data = [
            'sp_mchid' => $this->config['mch_id'],
            'sub_mchid' => $this->config['sub_mch_id'],
        ];

        $response = WxPayUtil::getV3Client($this->config)->post(
            "/v3/pay/partner/transactions/out-trade-no/$order_no/close",
            $data
        );

        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            return err($response['message'] ?? '请求失败！');
        }

        return $response;
    }

    public function refund(string $order_no, int $total, bool $is_transaction_id = false)
    {
        $data = [
            'sub_mchid' => $this->config['sub_mch_id'],
            'out_refund_no' => Util::random(32),
            'amount' => $total,
        ];

        if ($is_transaction_id) {
            $data['transaction_id'] = $order_no;
        } else {
            $data['out_trade_no'] = $order_no;
        }

        $response = WxPayUtil::getV3Client($this->config)->post(
            '/v3/refund/domestic/refunds',
            $data
        );

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
        ];

        $response = WxPayUtil::getV3Client($this->config)->post(
            "/v3/pay/partner/transactions/out-trade-no/$order_no",
            $data
        );

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