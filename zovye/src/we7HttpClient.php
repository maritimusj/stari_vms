<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use we7\ihttp;
use zovye\contract\IHttpClient;

class we7HttpClient implements IHttpClient
{
    /**
     * 请求指定网站，返回数据
     * @param string $url
     * @param string $method
     * @param string|array $headers
     * @param string|array $data
     * @param int $timeout
     * @return mixed
     */
    public function request(string $url, string $method = 'GET', $headers = '', $data = '', int $timeout = 60)
    {
        $extra = [
            'CURLOPT_CUSTOMREQUEST' => $method,
        ];
        if (is_array($headers)) {
            foreach ($headers as $name => $val) {
                if (is_string($name)) {
                    $extra[$name] = $val;
                } else {
                    $arr = explode(':', $val, 2);
                    if ($arr && $arr[0]) {
                        $extra[trim($arr[0])] = trim($arr[1]);
                    }
                }
            }
        }

        $http_options = [];
        if ($data) {
            $http_options['content'] = is_string($data) ? $data : http_build_query($data);
        }

        $resp = ihttp::request($url, $http_options['content'], $extra, $timeout);

        Log::debug(
            'we7httpClientRequest',
            [
                'url' => $url,
                'method' => $method,
                'header' => $headers,
                'body' => $data,
                'response' => $resp,
            ]
        );

        if (!is_error($resp)) {
            if ($resp['code'] == 200 && $resp['content']) {
                $result = json_decode($resp['content'], true);
                if ($result !== false && $result !== null) {
                    return $result;
                }
            }

            return err('请求失败！');
        }

        return err('系统维护中，请稍后再试！');
    }
}
