<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class HttpUtil
{
    /**
     * 使用GET请求指定API
     * @param string $url
     * @param int $timeout
     * @param array $params
     * @param bool $json_result
     * @return mixed
     */
    public static function get(string $url, int $timeout = 3, array $params = [], bool $json_result = false)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        if (stripos($url, "https://") !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if (empty($params[CURLOPT_USERAGENT])) {
            $params[CURLOPT_USERAGENT] = HTTP_USER_AGENT;
        }

        $headers = [];
        foreach ($params as $index => $val) {
            if ($index == CURLOPT_HTTPHEADER) {
                if (array($val)) {
                    $headers = array_merge($headers, $val);
                } else {
                    $headers[] = $val;
                }
                continue;
            }
            curl_setopt($ch, $index, $val);
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            return null;
        }

        return $json_result ? json_decode($response, true) : $response;
    }

    public static function getJSON(string $url)
    {
        return self::get($url, 3, [], true);
    }

    /**
     * 使用POST请求指定URL
     * @param string $url
     * @param array $data
     * @param bool $json
     * @param int $timeout
     * @param array $params
     * @return array
     */
    public static function post(
        string $url,
        array $data = [],
        bool $json = true,
        int $timeout = 3,
        array $params = []
    ): array {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        if (stripos($url, "https://") !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($ch, CURLOPT_POST, true);

        $headers = [];

        if ($json) {
            $json_str = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_str);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: '.strlen($json_str);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if (empty($params[CURLOPT_USERAGENT])) {
            $params[CURLOPT_USERAGENT] = HTTP_USER_AGENT;
        }

        foreach ($params as $index => $val) {
            if ($index == CURLOPT_HTTPHEADER) {
                if (array($val)) {
                    $headers = array_merge($headers, $val);
                } else {
                    $headers[] = $val;
                }
                continue;
            }
            curl_setopt($ch, $index, $val);
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);

        curl_close($ch);

        if (empty($response)) {
            return err('请求失败或者返回空数据！');
        }

        $result = json_decode($response, JSON_OBJECT_AS_ARRAY);

        return $result ?? err('无法解析返回的数据！');
    }
}