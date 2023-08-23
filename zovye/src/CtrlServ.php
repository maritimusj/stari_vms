<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\Contract\IHttpClient;

class CtrlServ
{
    const OK = 'Ok';
    const ABORT = 'abort';

    /**
     * @var $http_client IHttpClient
     */
    static $http_client = null;

    static $app = [];

    static $config = [];

    private function __construct()
    {
    }

    public static function init(IHttpClient $http_client, array $config)
    {
        self::$http_client = $http_client;
        self::$config = $config;
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
                    'nostr' => microtime(true).'',
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
    public static function query(
        string $path = '',
        array $params = [],
        $body = '',
        string $contentType = '',
        string $method = ''
    ) {
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
    public static function queryData(
        $version,
        $path,
        array $params = [],
        $body = '',
        string $contentType = '',
        string $method = ''
    ) {
        if (!self::$http_client) {
            return err('没有配置请求对象！');
        }

        $api_url = self::$config['url'];
        $app_key = self::$config['appKey'];
        $app_secret = self::$config['appSecret'];

        if (empty($api_url) || empty($app_key)) {
            return err('控制中心配置错误！');
        }

        if ($api_url[strlen($api_url) - 1] != '/') {
            $api_url .= '/';
        }

        $api_url .= $version;
        $api_url .= "/$path";

        if (empty($params['nostr'])) {
            $params['nostr'] = TIMESTAMP;
        }

        $api_url .= '?'.http_build_query($params);

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
            $sign = self::makeCtrlServerSignV1($app_key, $app_secret, $method, $params);
            $headers['llt-appkey'] = $app_key;
            $headers['llt-sign'] = $sign;
        } else {
            $headers['zovye-key'] = $app_key;
            $headers['zovye-sign'] = self::makeCtrlServerSign(
                $app_key,
                $app_secret,
                $params['nostr']
            );
        }

        return self::$http_client->request($api_url, $method, $headers, $body);
    }

    /**
     * 生成控制中心通信签名
     * @param $app_key
     * @param $app_secret
     * @param $method
     * @param array $params
     * @return string
     */
    public static function makeCtrlServerSignV1($app_key, $app_secret, $method, array $params): string
    {
        $params['appkey'] = $app_key;
        $params['appsecret'] = $app_secret;
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

    public static function status()
    {
        $res = self::getV2();
        if ($res) {
            if (is_error($res)) {
                return $res;
            }
            if ($res['status']) {
                return $res['data'];
            }
        }

        return err('请求失败！');
    }

    public static function deleteV2(string $path = '', array $params = [])
    {
        return self::queryData('v2', $path, $params, '', '', 'DELETE');
    }

    public static function getV2(string $path = '', array $params = [])
    {
        return self::queryData('v2', $path, $params);
    }

    public static function postV2(string $path = '', $body = [])
    {
        $body = is_string($body) ? $body : json_encode($body);

        return self::queryData('v2', $path, [], $body, 'application/json');
    }

    /**
     * 通知设备app更新设置
     * @param $app_id
     * @param string $op
     * @param array $payload
     * @return bool
     */
    public static function appNotify($app_id, string $op = 'config', array $payload = []): bool
    {
        if (empty(self::$app)) {
            register_shutdown_function(function () {
                foreach (self::$app as $data) {
                    $topic = "app/{$data['app']}";

                    $json = [
                        'op' => $data['op'],
                        'serial' => microtime(true).'',
                    ];

                    if ($data['data']) {
                        $json['data'] = $data['data'];
                    }

                    $body = json_encode(['topics' => [$topic], 'data' => json_encode($json)]);

                    $res = self::query('misc/publish', ['nostr' => sha1($body),], $body, 'application/json');

                    if (is_error($res)) {
                        Log::error('app_notify', [
                            'topic' => $topic,
                            'data' => $data,
                            'error' => $res,
                        ]);
                    }
                }
                self::$app = [];
            });
        }

        if ($app_id) {
            $key = sha1("$app_id:$op");
            if ($op == 'init' || $op == 'update') {
                self::$app[$key] = [
                    'app' => $app_id,
                    'op' => $op,
                ];
            } else {
                if (self::$app[$key]) {
                    $data = self::$app[$key]['data'] ?? [];
                    self::$app[$key]['data'] = array_merge_recursive($data, $payload);
                } else {
                    self::$app[$key] = [
                        'app' => $app_id,
                        'op' => $op,
                        'data' => $payload,
                    ];
                }
            }
        }

        return true;
    }

    /**
     * 根据设备分配数据，计算需要通知的APP, 并推送通知
     * @param array $original 原分配数据
     * @param array $data 新的分配数据
     * @param string $op 命令
     * @param array $payload 数据
     * @return bool
     */
    public static function appNotifyAll(
        array $original,
        array $data = [],
        string $op = 'update',
        array $payload = []
    ): bool {
        $topics = [];

        if ($data['all']) {
            if (!$original['all']) {
                $topics[] = 'tag/'.Topic::encrypt();
            }
        } else {
            if ($original['all']) {
                $topics[] = 'tag/'.Topic::encrypt();
            } else {
                if (isset($data['all']) && $data['all'] === false) {
                    $all = $original;
                } else {
                    $all = $data;
                    foreach ($original as $key => $entry) {
                        $all[$key] = $all[$key] ? array_merge(
                            array_diff($all[$key], $entry),
                            array_diff($entry, $all[$key])
                        ) : $entry;
                        if (empty($all[$key])) {
                            unset($all[$key]);
                        }
                    }
                }

                if ($all['agents']) {
                    foreach ($all['agents'] as $id) {
                        $topics[] = 'tag/'.Topic::encrypt("agent$id");
                    }
                }
                if ($all['groups']) {
                    foreach ($all['groups'] as $id) {
                        $topics[] = 'tag/'.Topic::encrypt("group$id");
                    }
                }
                if ($all['tags']) {
                    foreach ($all['tags'] as $id) {
                        $topics[] = 'tag/'.Topic::encrypt("tag$id");
                    }
                }
                if ($all['devices']) {
                    foreach ($all['devices'] as $id) {
                        $topics[] = 'tag/'.Topic::encrypt("device$id");
                    }
                }
            }
        }

        if ($topics) {
            $data = [
                'op' => $op,
                'serial' => microtime(true).'',
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
     * @param array $params
     * @return bool
     */
    public static function checkJobSign(array $params = []): bool
    {
        $params = is_array($params) ? $params : [$params];

        $data = array_merge(
            $params,
            [
                'op' => Request::trim('op'),
                'm' => APP_NAME,
                'serial' => Request::str('serial'),
                'do' => 'job',
            ]
        );

        return Request::str('sign') === self::makeSign($data);
    }

    /**
     * 生成签名
     * @param array $params
     * @return string
     */
    public static function makeSign(array $params = []): string
    {
        ksort($params);

        return sha1(http_build_query($params).self::$config['signature']);
    }

    private static function makeJobUrl($op, array $params = []): string
    {
        $params['do'] = 'job';
        $params['op'] = $op;
        $params['m'] = APP_NAME;
        $params['serial'] = microtime(true).'';

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
            return $result['queued'] ?? true;
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
        $body = [
            'type' => $level,
            'url' => $url,
            'data' => $data,
            'content-type' => 'application/json',
        ];

        $uid = Util::random(16, true);

        return self::query("job/queue/$uid", [], $body);
    }

    /**
     * 在队列中加入一个回调任务
     * @param string $url
     * @param string $type
     * @param int $delay
     * @param int $freq
     * @param mixed $data
     * @return mixed
     */
    public static function httpCallback(
        string $url,
        string $type = 'normal',
        int $delay = 0,
        int $freq = 0,
        $data = ''
    ) {
        $body = [
            'type' => $type,
            'url' => $url,
            'data' => is_string($data) ? $data : json_encode($data),
            'content-type' => 'application/json',
        ];

        if ($delay > 0) {
            $body['delay'] = $delay;
        }

        if ($freq > 0) {
            $body['freq'] = $freq;
        }

        return self::postV2("job", $body);
    }

    /**
     * 在队列中加入一个回调任务
     * @param string $url
     * @param string $type
     * @param string $spec
     * @param mixed $data
     * @return mixed
     */
    public static function httpCallbackCron(string $url, string $type, string $spec = '', $data = '')
    {
        $body = [
            'url' => $url,
            'type' => $type,
            'spec' => $spec,
            'data' => is_string($data) ? $data : json_encode($data),
            'content-type' => 'application/json',
        ];

        return self::postV2("cron", $body);
    }
}
