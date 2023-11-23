<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\payment;

use Exception;
use GuzzleHttp\Exception\RequestException;
use zovye\Pay;
use zovye\util\PayUtil;
use function zovye\err;

class WxPayV3Partner extends WxPayV3
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
                'sp_appid' => $this->config['appid'],
                'sp_mchid' => $this->config['mch_id'],
                'sub_mchid' => $this->config['sub_mch_id'],
                'description' => $body,
                'out_trade_no' => $order_no,
                'notify_url' => parent::getCallbackUrl(),
                'amount' => [
                    'total' => $price,
                    'currency' => 'CNY',
                ],
                'payer' => [
                    'sp_openid' => $user_uid,
                ],
                'attach' => $device_uid,
            ],
        ];

        try {
            $response = parent::builder()
                ->v3->pay->partner->transactions->jsapi
                ->post($data);

            return parent::parseJSPayResponse($response);

        } catch (Exception $e) {
            if ($e instanceof RequestException) {
                $res = PayUtil::parseWxPayV3Response($e->getResponse());

                return err($res['message'] ?? '请求失败！');
            }

            return err($e->getMessage());
        }
    }

    public function close(string $order_no)
    {
        $data = [
            'json' => [
                'sp_mchid' => $this->config['mch_id'],
                'sub_mchid' => $this->config['sub_mch_id'],
            ],
            'order_no' => $order_no,
        ];

        try {
            $response = parent::builder()
                ->v3->pay->partner->transactions->outTradeNo->_order_no_->close
                ->post($data);

            $result = PayUtil::parseWxPayV3Response($response);

            if (!empty($result['code'])) {
                return err($result['message'] ?? '请求失败！');
            }

            return $result;

        } catch (Exception $e) {
            if ($e instanceof RequestException) {
                $res = PayUtil::parseWxPayV3Response($e->getResponse());

                return err($res['message'] ?? '请求失败！');
            }

            return err($e->getMessage());
        }
    }

    public function query(string $order_no): array
    {
        $data = [
            'query' => [
                'sp_mchid' => $this->config['mch_id'],
                'sub_mchid' => $this->config['sub_mch_id'],
            ],
            'order_no' => $order_no,
        ];

        try {
            $response = parent::builder()
                ->v3->pay->partner->transactions->outTradeNo->_order_no_
                ->get($data);

            return parent::parseQueryResponse($response);

        } catch (Exception $e) {
            if ($e instanceof RequestException) {
                $res = PayUtil::parseWxPayV3Response($e->getResponse());

                return err($res['message'] ?? '请求失败！');
            }

            return err($e->getMessage());
        }
    }
}