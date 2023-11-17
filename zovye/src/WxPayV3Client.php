<?php

namespace zovye;

use Exception;
use GuzzleHttp\Exception\RequestException;
use WeChatPay\BuilderChainable;

class WxPayV3Client
{
    /** @var BuilderChainable */
    private $instance;

    public function __construct(BuilderChainable $instance)
    {
        $this->instance = $instance;
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