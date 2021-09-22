<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\Contract\IHttpClient;

class CtrlServ
{
    const HANDLE_OK = 'Ok';

    /**
     * @var $http_client IHttpClient
     */
    static $http_client = null;

    private function __construct()
    {
    }

    public static function setHttpClient(IHttpClient $http_client)
    {
        self::$http_client = $http_client;
    }

    /**
     * @param $app_key
     * @param $app_secret
     * @param $nostr
     * @param $payload
     * @return string
     */
    public static function makeNotifierSign($app_key, $app_secret, $nostr, $payload): string
    {
        return hash_hmac("sha256", "$app_key$nostr$payload", $app_secret);
    }

    /**
     * 通知设备mcb更新设置
     * @param $mcbUID
     * @param $code
     * @param string $op
     * @param array $payloadData
     * @return bool
     */
    public static function mcbNotify($mcbUID, $code, string $op = 'params', array $payloadData = []): bool
    {
        if ($mcbUID) {

            $topic = ["v1/device/mcb/$mcbUID/$code"];
            $data = json_encode(
                [
                    'op' => $op,
                    'nostr' => microtime(true) . '',
                    'da' => $payloadData,
                ]
            );

            $body = json_encode(['topics' => $topic, 'data' => $data]);

            $res = self::query('misc/publish', ['nostr' => sha1($body)], $body, 'application/json');

            return !is_error($res);
        }

        return false;
    }

    /**
     * 与控制中心交互 api版本v1
     * @param string $path
     * @param array $params
     * @param mixed $body
     * @param string $contentType
     * @param string $method
     * @return mixed
     */
    public static function query(string $path = '', array $params = [], $body = '', string $contentType = '', string $method = '')
    {
        return self::queryData('v1', $path, $params, $body, $contentType, $method);
    }

    /**
     * 与控制中心交互
     * @param $version
     * @param $path
     * @param array $params
     * @param mixed $body
     * @param string $contentType
     * @param string $method
     * @return mixed
     */
    public static function queryData($version, $path, array $params = [], $body = '', string $contentType = '', string $method = '')
    {
        if (self::$http_client) {
            $ctrlServerUrl = settings('ctrl.url');
            $appKey = settings('ctrl.appKey');

            if (empty($ctrlServerUrl) || empty($appKey)) {
                return error(State::ERROR, '控制中心配置错误！');
            }

            if ($ctrlServerUrl[strlen($ctrlServerUrl) - 1] != '/') {
                $ctrlServerUrl .= '/';
            }

            $ctrlServerUrl .= $version;
            $ctrlServerUrl .= "/$path";

            $params['nostr'] = TIMESTAMP;
            $ctrlServerUrl .= '?' . http_build_query($params);

            $headers = [];

            if ($body) {
                if (empty($method)) {
                    $method = 'POST';
                }

                if (is_string($body)) {
                    if (empty($contentType)) {
                        $contentType = 'application/x-www-form-urlencoded';

                    }
                    if ($contentType == 'application/x-www-form-urlencoded') {
                        parse_str($body, $result);
                        $params = array_merge($params, $result);
                    }
                } else {
                    $contentType = 'application/json';
                    $body = json_encode($body);
                }

                if ($contentType) {
                    $headers['Content-Type'] = $contentType;
                }

            } else {
                if (empty($method)) {
                    $method = 'GET';
                }
            }

            if ($version == 'v1') {
                $sign = self::makeCtrlServerSignV1($method, $params);

                $headers['llt-appkey'] = $appKey;
                $headers['llt-sign'] = $sign;
            } else {
                $headers['zovye-key'] = $appKey;
                $headers['zovye-sign'] = self::makeCtrlServerSign(settings('ctrl.appKey', ''), settings('ctrl.appSecret', ''), $params['nostr']);                
            }

            return self::$http_client->request($ctrlServerUrl, $method, $headers, $body);
        }

        return error(State::ERROR, '没有配置请求对象！');
    }

    /**
     * 生成控制中心通信签名
     * @param $method
     * @param array $params
     * @return string
     */
    public static function makeCtrlServerSignV1($method, array $params): string
    {
        $params['appkey'] = settings('ctrl.appKey');
        $params['appsecret'] = settings('ctrl.appSecret');
        $params['method'] = $method;

        ksort($params);

        return sha1(strtolower(http_build_query($params)));
    }

    /**
     * 生成控制中心通信签名
     * @param $app_key
     * @param $app_secret
     * @param string $nostr
     * @return string
     */
    public static function makeCtrlServerSign($app_key, $app_secret, string $nostr = TIMESTAMP): string
    {
        return hash_hmac("sha256", "$app_key$nostr", $app_secret);
    }

    /**
     * 与控制中心交互 api版本v2
     * @param string $path
     * @param array $params
     * @param mixed $body
     * @param string $contentType
     * @param string $method
     * @return mixed
     */
    public static function v2_query(string $path, array $params = [], $body = '', string $contentType = '', string $method = '')
    {
        return self::queryData('v2', $path, $params, $body, $contentType, $method);
    }

    /**
     * 通知设备app更新设置
     * @param $app_id
     * @param string $op
     * @param array $payload
     * @return bool
     */
    public static function appNotify($app_id, string $op = 'update', array $payload = []): bool
    {
        if ($app_id) {
            $topic = ["app/$app_id"];
            
            $data = [
                'op' => $op,
                'serial' => microtime(true) . '',
            ];

            if ($payload) {
                $data['data'] = $payload;
            }

            $body = json_encode(['topics' => $topic, 'data' => json_encode($data)]);

            $res = self::query('misc/publish', ['nostr' => sha1($body),], $body, 'application/json');

            return !is_error($res);
        }

        return false;
    }

    /**
     * 根据设备分配数据，计算需要通知的APP, 并推送通知
     * @param array $original 原分配数据
     * @param array $data 新的分配数据
     * @param string $op 命令
     * @param array $payload 数据
     * @return bool
     */
    public static function appNotifyAll(array $original, array $data = [], string $op = 'update', array $payload = []): bool
    {
        $topics = [];

        if ($data['all']) {
            if (!$original['all']) {
                $topics[] = 'tag/' . Topic::encrypt();
            }
        } else {
            if ($original['all']) {
                $topics[] = 'tag/' . Topic::encrypt();
            } else {
                if (isset($data['all']) && $data['all'] === false) {
                    $all = $original;
                } else {
                    $all = $data;
                    foreach ($original as $key => $entry) {
                        $all[$key] = $all[$key] ? array_merge(array_diff($all[$key], $entry), array_diff($entry, $all[$key])) : $entry;
                        if (empty($all[$key])) {
                            unset($all[$key]);
                        }
                    }
                }

                if ($all['agents']) {
                    foreach ($all['agents'] as $id) {
                        $topics[] = 'tag/' . Topic::encrypt("agent$id");
                    }
                }
                if ($all['groups']) {
                    foreach ($all['groups'] as $id) {
                        $topics[] = 'tag/' . Topic::encrypt("group$id");
                    }
                }
                if ($all['tags']) {
                    foreach ($all['tags'] as $id) {
                        $topics[] = 'tag/' . Topic::encrypt("tag$id");
                    }
                }
                if ($all['devices']) {
                    foreach ($all['devices'] as $id) {
                        $topics[] = 'tag/' . Topic::encrypt("device$id");
                    }
                }
            }
        }

        if ($topics) {
            $data = [
                'op' => $op,
                'serial' => microtime(true) . '',
            ];

            if ($payload) {
                $data['data'] = $payload;
            }

            $body = [
                'topics' => $topics,
                'data' => json_encode($data),
            ];

            $res = self::query('misc/publish', [], $body);
            return !is_error($res);
        }

        return true;
    }

    /**
     * 检查job参数的签名
     * @param $params
     * @return bool
     */
    public static function checkJobSign($params): bool
    {
        $params = is_array($params) ? $params : [$params];

        $data = array_merge(
            $params,
            [
                'op' => request::trim('op'),
                'm' => APP_NAME,
                'serial' => request::str('serial'),
                'do' => 'job',
            ]
        );

        return request('sign') === self::makeSign($data);
    }

    /**
     * 生成签名
     * @param array $params
     * @return string
     */
    public static function makeSign(array $params = []): string
    {
        ksort($params);

        return sha1(http_build_query($params) . settings('ctrl.signature'));
    }

    private static function makeJobUrl($op, array $params = []): string
    {
        $params['do'] = 'job';
        $params['op'] = $op;
        $params['m'] = APP_NAME;
        $params['serial'] = microtime(true) . '';

        $params['sign'] = self::makeSign($params);
        return Util::murl('job', $params);
    }

    /**
     * 创建一个延时回调任务
     * @param $op
     * @param array $params
     * @param int $delay
     * @return bool|mixed
     */
    public static function scheduleDelayJob($op, array $params = [], int $delay = 0)
    {
        $result = self::httpDelayCallback($delay, self::makeJobUrl($op, $params));
        if (!is_error($result) && $result !== false) {
            return $result['queued'] ?? true;
        }
        return false;
    }

    /**
     * 加入一个延时回调任务
     * @param $delay
     * @param $url
     * @param string $data
     * @return mixed
     */
    public static function httpDelayCallback($delay, $url, string $data = '')
    {
        $uid = Util::random(16);
        $query = [
            'delay' => intval($delay),
            'url' => $url,
            'data' => $data,
        ];

        return self::query("job/delay/$uid", [], http_build_query($query));
    }

    /**
     * 创建一个优先级回调任务
     * @param $op
     * @param array $params
     * @param string $level
     * @return mixed
     */
    public static function scheduleJob($op, array $params = [], string $level = LEVEL_NORMAL)
    {
        $result = self::httpQueuedCallback($level, self::makeJobUrl($op, $params));

        if (!is_error($result) && $result !== false) {
            return $result['queued'];
        }
        return false;
    }

    /**
     * 在队列中加入一个回调任务
     * @param $level
     * @param $url
     * @param string $data
     * @return mixed
     */
    public static function httpQueuedCallback($level, $url, string $data = '')
    {
        $query = [
            'type' => $level,
            'url' => $url,
            'data' => $data,
        ];

        $uid = Util::random(16, true);

        return self::query("job/queue/$uid", [], http_build_query($query));
    }
}
