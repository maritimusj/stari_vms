<?php

namespace zovye;

use Exception;
use GuzzleHttp\Exception\RequestException;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

require_once MODULE_ROOT.'vendor/autoload.php';

class WxPayV3
{
    /** @var BuilderChainable */
    private $instance;

    private function __construct(BuilderChainable $instance)
    {
        $this->instance = $instance;
    }

    public static function getClient(array $config): self
    {
        // 从「微信支付平台证书」中获取「证书序列号」
        $serial = PemUtil::parseCertificateSerialNo($config['pem']['cert']);

        // 构造一个 APIv3 客户端实例
        $instance = Builder::factory([
            'mchid' => $config['mch_id'],         // 商户号
            'serial' => $config['serial'],        // 「商户API证书」的「证书序列号」
            'privateKey' => Rsa::from($config['pem']['key']),
            'certs' => [
                $serial => Rsa::from($config['pem']['cert'], Rsa::KEY_TYPE_PUBLIC),
            ],
        ]);

        return new WxPayV3($instance);
    }

    public function get($path, $data = [])
    {
        return $this->query('get', $path, $data);
    }

    public function post($path, $data = [])
    {
        return $this->query('post', $path, $data);
    }

    public function query($method, $path, $data = [])
    {
        try {
            if ($method == 'post') {
                $response = $this->instance->chain($path)->post(['json' => $data]);
            } elseif ($method == 'get') {
                $response = $this->instance->chain($path)->get(['query' => $data]);
            } else {
                return err('暂不支持的http方法:'.$method);
            }

            $contents = $response->getBody()->getContents();
            if ($contents) {
                return json_decode($contents, true);
            }
        } catch (Exception $e) {
            Log::error('wx_pay_v3', [
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof RequestException && $e->hasResponse()) {
                $r = $e->getResponse();
                $contents = $r->getBody()->getContents();

                return json_decode($contents, true);
            }
        }

        return err('请求失败，请稍后再试！');
    }
}