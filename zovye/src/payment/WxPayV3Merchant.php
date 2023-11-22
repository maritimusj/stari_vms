<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\payment;

use zovye\Pay;
use zovye\util\PayUtil;
use function zovye\err;

class WxPayV3Merchant extends WxPayV3
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
            'json' => [
                'appid' => $this->config['appid'],
                'mchid' => $this->config['mch_id'],
                'description' => $body,
                'out_trade_no' => $order_no,
                'notify_url' => parent::getCallbackUrl(),
                'amount' => [
                    'total' => $price,
                    'currency' => 'CNY',
                ],
                'payer' => [
                    'openid' => $user_uid,
                ],
                'attach' => $device_uid,
            ],
        ];

        $response = parent::builder()->v3->pay->transactions->jsapi->post($data);

        return parent::parseJSPayResponse($response);
    }

    public function close(string $order_no)
    {
        $data = [
            'json' => [
                'mchid' => $this->config['mch_id'],
            ],
            'order_no' => $order_no,
        ];

        $response = parent::builder()
            ->v3->pay->transactions->outTradeNo->_order_no_->close
            ->post($data);

        $result = PayUtil::parseWxPayV3Response($response);

        if (!empty($result['code'])) {
            return err($result['message'] ?? '请求失败！');
        }

        return $result;
    }

    public function query(string $order_no): array
    {
        $data = [
            'query' => [
                'mchid' => $this->config['mch_id'],
            ],
            'order_no' => $order_no,
        ];

        $response = parent::builder()
            ->v3->pay->transactions->outTradeNo->_order_no_
            ->get($data);

        return parent::parseQueryResponse($response);
    }
}