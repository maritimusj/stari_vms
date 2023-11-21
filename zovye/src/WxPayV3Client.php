<?php

namespace zovye;

use Exception;
use GuzzleHttp\Exception\RequestException;
use WeChatPay\BuilderChainable;
use zovye\util\PayUtil;

class WxPayV3Client
{
    /** @var BuilderChainable */
    private $instance;

    public function __construct(BuilderChainable $instance)
    {
        $this->instance = $instance;
    }

    public function instance(): BuilderChainable
    {
        return $this->instance;
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

            return PayUtil::parseWxPayV3Response($response);

        } catch (Exception $e) {
            Log::error('wx_pay_v3', [
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof RequestException && $e->hasResponse()) {
                return PayUtil::parseWxPayV3Response($e->getResponse());
            }
        }

        return err('请求失败，请稍后再试！');
    }
}