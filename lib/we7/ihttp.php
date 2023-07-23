<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace we7;

use CURLFile;
use zovye\Util;
use zovye\We7;
use function zovye\_W;
use function zovye\error;
use function zovye\is_error;

defined('IN_IA') or exit('Access Denied');

class ihttp
{
    /**
     * 模拟 http 请求
     *
     * @param string $url 请求URL地址
     * @param string $post 请求数据
     * @param array $extra header 参数
     * @param int $timeout 超时时间
     *
     * @return array http响应封装信息或错误信息
     */
    public static function request($url, $post = '', $extra = array(), $timeout = 60)
    {
        //timeout为0时，相当于是非阻塞异步请求
        //curl不支持timeout为0的情况，需要使用fsockopen来处理
        if (function_exists('curl_init') && function_exists('curl_exec') && $timeout > 0) {
            $ch = self::build_curl($url, $post, $extra, $timeout);
            if (is_error($ch)) {
                return $ch;
            }
            $data = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno || empty($data)) {
                return error($errno, $error);
            } else {
                return self::response_parse($data);
            }
        }
        $urlset = self::parse_url($url, true);
        if (!empty($urlset['ip'])) {
            $urlset['host'] = $urlset['ip'];
        }

        $body = self::build_httpbody($url, $post, $extra);

        if ('https' == $urlset['scheme']) {
            $fp = self::socketopen('ssl://' . $urlset['host'], $urlset['port'], $errno, $error);
        } else {
            $fp = self::socketopen($urlset['host'], $urlset['port'], $errno, $error);
        }
        stream_set_blocking($fp, $timeout > 0);
        stream_set_timeout($fp, ini_get('default_socket_timeout'));
        if (!$fp) {
            return error(1, $error);
        } else {
            fwrite($fp, $body);
            $content = '';
            if ($timeout > 0) {
                while (!feof($fp)) {
                    $content .= fgets($fp, 512);
                }
            }
            fclose($fp);

            return self::response_parse($content, true);
        }
    }

    /**
     * 封装的 GET 请求方法.
     *
     * @param string $url 请求URL地址
     *
     * @return array
     */
    public static function get($url)
    {
        return self::request($url);
    }

    /**
     * 封装的POST请求方法.
     *
     * @param string $url 请求URL地址
     * @param array $data 请求数据
     *
     * @return array
     */
    public static function post($url, $data)
    {
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');

        return self::request($url, $data, $headers);
    }

    /**
     * 非阻塞并发请求多个URL地址
     *
     * @param array $urls 请求多个地址数组
     * @param array $posts 请求多个地址对应的POST数据
     *                       二维数据组时，需要与URL键值一一对应，每个请求不同的POST数据
     *                       一维数组时，每个请求使用此数据
     * @param array $extra 同 self::request
     * @param int $timeout 同 self::request
     *
     * @return array 返回结果与url键值对应结果数组
     */
    public static function multi_request($urls, $posts = array(), $extra = array(), $timeout = 60)
    {
        if (!is_array($urls)) {
            return error(1, '请使用self::request函数');
        }
        $curl_multi = curl_multi_init();
        $curl_client = $response = array();

        foreach ($urls as $i => $url) {
            if (isset($posts[$i]) && is_array($posts[$i])) {
                $post = $posts[$i];
            } else {
                $post = $posts;
            }
            if (!empty($url)) {
                $curl = self::build_curl($url, $post, $extra, $timeout);
                if (is_error($curl)) {
                    continue;
                }
                if (CURLM_OK === curl_multi_add_handle($curl_multi, $curl)) {
                    //存到数据组中，方便之后获取结果
                    $curl_client[] = $curl;
                }
            }
        }
        if (!empty($curl_client)) {
            $active = null;
            do {
                $mrc = curl_multi_exec($curl_multi, $active);
            } while (CURLM_CALL_MULTI_PERFORM == $mrc);

            while ($active && CURLM_OK == $mrc) {
                do {
                    $mrc = curl_multi_exec($curl_multi, $active);
                } while (CURLM_CALL_MULTI_PERFORM == $mrc);
            }
        }

        foreach ($curl_client as $i => $curl) {
            $response[$i] = curl_multi_getcontent($curl);
            curl_multi_remove_handle($curl_multi, $curl);
        }
        curl_multi_close($curl_multi);

        return $response;
    }

    static function socketopen($hostname, $port, &$errno, &$errstr, $timeout = 15)
    {
        $fp = '';
        if (function_exists('fsockopen')) {
            $fp = @fsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('pfsockopen')) {
            $fp = @pfsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('stream_socket_client')) {
            $fp = @stream_socket_client($hostname . ':' . $port, $errno, $errstr, $timeout);
        }

        return $fp;
    }

    /**
     * 对请求得到的数据的进行分析和封装.
     *
     * @param string $data
     * @param bool $chunked
     *
     * @return array error 或 $data 的封装
     */
    static function response_parse($data, $chunked = false)
    {
        $rlt = array();

        $pos = strpos($data, "\r\n\r\n");
        $split1[0] = substr($data, 0, $pos);
        $split1[1] = substr($data, $pos + 4, strlen($data));

        $split2 = explode("\r\n", $split1[0], 2);
        preg_match('/^(\S+) (\S+) (.*)$/', $split2[0], $matches);
        $rlt['code'] = !empty($matches[2]) ? $matches[2] : 200;
        $rlt['status'] = !empty($matches[3]) ? $matches[3] : 'OK';
        $rlt['responseline'] = !empty($split2[0]) ? $split2[0] : '';
        $header = explode("\r\n", $split2[1]);
        $isgzip = false;
        $ischunk = false;
        foreach ($header as $v) {
            $pos = strpos($v, ':');
            $key = substr($v, 0, $pos);
            $value = trim(substr($v, $pos + 1));
            if (isset($rlt['headers'][$key]) && is_array($rlt['headers'][$key])) {
                $rlt['headers'][$key][] = $value;
            } elseif (!empty($rlt['headers'][$key])) {
                $temp = $rlt['headers'][$key];
                unset($rlt['headers'][$key]);
                $rlt['headers'][$key][] = $temp;
                $rlt['headers'][$key][] = $value;
            } else {
                $rlt['headers'][$key] = $value;
            }
            if (!$isgzip && 'content-encoding' == strtolower($key) && 'gzip' == strtolower($value)) {
                $isgzip = true;
            }
            if (!$ischunk && 'transfer-encoding' == strtolower($key) && 'chunked' == strtolower($value)) {
                $ischunk = true;
            }
        }
        if ($chunked && $ischunk) {
            $rlt['content'] = self::response_parse_unchunk($split1[1]);
        } else {
            $rlt['content'] = $split1[1];
        }
        if ($isgzip && function_exists('gzdecode')) {
            $rlt['content'] = gzdecode($rlt['content']);
        }

        $rlt['meta'] = $data;
        if ('100' == $rlt['code']) {
            return self::response_parse($rlt['content']);
        }

        return $rlt;
    }

    static function response_parse_unchunk($str = null)
    {
        if (!is_string($str) or strlen($str) < 1) {
            return false;
        }
        $eol = "\r\n";
        $add = strlen($eol);
        $tmp = $str;
        $str = '';
        do {
            $tmp = ltrim($tmp);
            $pos = strpos($tmp, $eol);
            if (false === $pos) {
                return false;
            }
            $len = hexdec(substr($tmp, 0, $pos));
            if (!is_numeric($len) or $len < 0) {
                return false;
            }
            $str .= substr($tmp, ($pos + $add), $len);
            $tmp = substr($tmp, ($len + $pos + $add));
            $check = trim($tmp);
        } while (!empty($check));
        unset($tmp);

        return $str;
    }

    /**
     * 格式化请求URL.
     *
     * @param string $url 要格式化检查的URL
     * @param bool $set_default_port 是否根据协议指定默认端口
     *
     * @return array
     */
    static function parse_url($url, $set_default_port = false)
    {
        if (empty($url)) {
            return error(1);
        }
        $urlset = parse_url($url);
        if (!empty($urlset['scheme']) && !in_array($urlset['scheme'], array('http', 'https'))) {
            return error(1, '只能使用 http 及 https 协议');
        }
        if (empty($urlset['path'])) {
            $urlset['path'] = '/';
        }
        if (!empty($urlset['query'])) {
            $urlset['query'] = "?{$urlset['query']}";
        }
        if (We7::str_exists($url, 'https://') && !extension_loaded('openssl')) {
            if (!extension_loaded('openssl')) {
                return error(1, '请开启您PHP环境的openssl', '');
            }
        }
        if (empty($urlset['host'])) {
            $current_url = parse_url(_W('siteroot'));
            $urlset['host'] = $current_url['host'];
            $urlset['scheme'] = $current_url['scheme'];
            $urlset['path'] = $current_url['path'] . 'web/' . str_replace('./', '', $urlset['path']);
            $urlset['ip'] = '127.0.0.1';
        } elseif (!self::allow_host($urlset['host'])) {
            return error(1, 'host 非法');
        }

        if ($set_default_port && empty($urlset['port'])) {
            $urlset['port'] = 'https' == $urlset['scheme'] ? '443' : '80';
        }

        return $urlset;
    }

    /**
     *  是否允许指定host访问.
     *
     * @param $host
     *
     * @return bool
     */
    static function allow_host($host)
    {
        global $_W;
        if (We7::str_exists($host, '@')) {
            return false;
        }
        $pattern = '/^(10|172|192|127)/';
        if (preg_match($pattern, $host) && isset($_W['setting']['ip_white_list'])) {
            $ip_white_list = $_W['setting']['ip_white_list'];
            if ($ip_white_list && isset($ip_white_list[$host]) && !$ip_white_list[$host]['status']) {
                return false;
            }
        }

        return true;
    }

    /**
     * 创建一个curl请求对象
     * 参数同 self::request.
     * @param $url
     * @param $post
     * @param $extra
     * @param $timeout
     * @return array|false|resource
     */
    static function build_curl($url, $post, $extra, $timeout)
    {
        if (!function_exists('curl_init') || !function_exists('curl_exec')) {
            return error(1, 'curl扩展未开启');
        }

        $urlset = self::parse_url($url);
        if (is_error($urlset)) {
            return $urlset;
        }

        if (!empty($urlset['ip'])) {
            $extra['ip'] = $urlset['ip'];
        }

        $ch = curl_init();
        if (!empty($extra['ip'])) {
            $extra['Host'] = $urlset['host'];
            $urlset['host'] = $extra['ip'];
            unset($extra['ip']);
        }
        curl_setopt($ch, CURLOPT_URL, $urlset['scheme'] . '://' . $urlset['host'] . (empty($urlset['port']) || '80' == $urlset['port'] ? '' : ':' . $urlset['port']) . $urlset['path'] . (!empty($urlset['query']) ? $urlset['query'] : ''));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        @curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        if ($post) {
            if (is_array($post)) {
                $filepost = false;
                // 5.6版本后，'@'上传，使用CURLFile替代
                foreach ($post as $name => &$value) {
                    if (version_compare(phpversion(), '5.5') >= 0 && is_string($value) && '@' == substr($value, 0, 1)) {
                        $post[$name] = new CURLFile(ltrim($value, '@'));
                    }
                    if ((is_string($value) && '@' == substr($value, 0, 1)) || (class_exists('CURLFile') && $value instanceof CURLFile)) {
                        $filepost = true;
                    }
                }
                if (!$filepost) {
                    $post = http_build_query($post);
                }
            }
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if (!empty(_W('config.setting.proxy'))) {
            $urls = parse_url(_W('config.setting.proxy.host'));
            if (!empty($urls['host'])) {
                curl_setopt($ch, CURLOPT_PROXY, "{$urls['host']}:{$urls['port']}");
                $proxytype = 'CURLPROXY_' . strtoupper($urls['scheme']);
                if (!empty($urls['scheme']) && defined($proxytype)) {
                    curl_setopt($ch, CURLOPT_PROXYTYPE, constant($proxytype));
                } else {
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
                }
                if (!empty(_W('config.setting.proxy.auth'))) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, _W('config.setting.proxy.auth'));
                }
            }
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        if (defined('CURL_SSLVERSION_TLSv1')) {
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
        if (!empty($extra) && is_array($extra)) {
            $headers = array();
            foreach ($extra as $opt => $value) {
                if (We7::str_exists($opt, 'CURLOPT_')) {
                    curl_setopt($ch, constant($opt), $value);
                } elseif (is_numeric($opt)) {
                    curl_setopt($ch, $opt, $value);
                } else {
                    $headers[] = "{$opt}: {$value}";
                }
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
        }

        return $ch;
    }

    static function build_httpbody($url, $post, $extra)
    {
        $urlset = self::parse_url($url, true);
        if (is_error($urlset)) {
            return $urlset;
        }

        if (!empty($urlset['ip'])) {
            $extra['ip'] = $urlset['ip'];
        }

        $body = '';
        if (!empty($post) && is_array($post)) {
            $filepost = false;
            $boundary = Util::random(40);
            foreach ($post as $name => &$value) {
                if ((is_string($value) && '@' == substr($value, 0, 1)) && file_exists(ltrim($value, '@'))) {
                    $filepost = true;
                    $file = ltrim($value, '@');

                    $body .= "--$boundary\r\n";
                    $body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . basename($file) . '"; Content-Type: application/octet-stream' . "\r\n\r\n";
                    $body .= file_get_contents($file) . "\r\n";
                } else {
                    $body .= "--$boundary\r\n";
                    $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
                    $body .= $value . "\r\n";
                }
            }
            if (!$filepost) {
                $body = http_build_query($post, '', '&');
            } else {
                $body .= "--$boundary\r\n";
            }
        }

        $method = empty($post) ? 'GET' : 'POST';
        $fdata = "{$method} {$urlset['path']}{$urlset['query']} HTTP/1.1\r\n";
        $fdata .= "Accept: */*\r\n";
        $fdata .= "Accept-Language: zh-cn\r\n";
        if ('POST' == $method) {
            $fdata .= empty($filepost) ? "Content-Type: application/x-www-form-urlencoded\r\n" : "Content-Type: multipart/form-data; boundary=$boundary\r\n";
        }
        $fdata .= "Host: {$urlset['host']}\r\n";
        $fdata .= "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1\r\n";
        if (function_exists('gzdecode')) {
            $fdata .= "Accept-Encoding: gzip, deflate\r\n";
        }
        $fdata .= "Connection: close\r\n";
        if (!empty($extra) && is_array($extra)) {
            foreach ($extra as $opt => $value) {
                if (!We7::str_exists($opt, 'CURLOPT_')) {
                    $fdata .= "{$opt}: {$value}\r\n";
                }
            }
        }
        if ($body) {
            $fdata .= 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}";
        } else {
            $fdata .= "\r\n";
        }

        return $fdata;
    }
}
