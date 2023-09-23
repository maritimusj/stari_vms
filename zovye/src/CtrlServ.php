<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\contract\IHttpClient;
use zovye\util\Util;

class CtrlServ
{
    const OK = 'Ok';
    const ABORT = 'abort';

    /**
     * @var $http_client IHttpClient
     */
    static $http_client = null;

    static $app = [];

    static $default_config = [];

    private function __construct()
    {
    }

    public static function init(IHttpClient $http_client, array $config)
    {
        self::$http_client = $http_client;
        self::$default_config = $config;
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

        return [];
    }

    public static function detail($device_uid)
    {
        return self::query("device/$device_uid");
    }

    public static function bind($device_uid, $app_uid)
    {
        $params = http_build_query(['appUID' => $app_uid]);

        return CtrlServ::query("device/$device_uid/bind", [], $params, '', 'PUT');
    }

    public static function active($device_uid)
    {
        return CtrlServ::query("device/$device_uid/active", [], '', '', 'PUT');
    }

    public static function createOrder($order_no, $body)
    {
        $params = ['nostr' => microtime(true)];

        return CtrlServ::query("order/$order_no", $params, http_build_query($body));
    }

    /**
     * 通知设备mcb更新设置
     */
    public static function mcbPublish($mcb_uid, $code, string $op = 'params', array $payload = []): bool
    {
        if ($mcb_uid) {

            $topic = "v1/device/mcb/$mcb_uid/$code";
            $data = json_encode(
                [
                    'op' => $op,
                    'nostr' => microtime(true).'',
                    'da' => $payload,
                ]
            );

            $body = json_encode(['topics' => [$topic], 'data' => $data]);
            $params = ['nostr' => sha1($body)];

            $res = self::query('misc/publish', $params, $body, 'application/json');

            return !is_error($res);
        }

        return false;
    }

    /**
     * 通知设备app更新设置
     */
    public static function appPublish($app_id, string $op = 'config', array $payload = []): bool
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
                    $params = ['nostr' => sha1($body)];

                    $res = self::query('misc/publish', $params, $body, 'application/json');

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
     */
    public static function appPublishAll(
        array $original,
        array $data = [],
        string $op = 'update',
        array $payload = []
    ): bool {
        $topics = [];

        if ($data['all']) {
            if (!$original['all']) {
                $topics[] = 'tag/'.Util::encryptTopic();
            }
        } else {
            if ($original['all']) {
                $topics[] = 'tag/'.Util::encryptTopic();
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
                        $topics[] = 'tag/'.Util::encryptTopic("agent$id");
                    }
                }
                if ($all['groups']) {
                    foreach ($all['groups'] as $id) {
                        $topics[] = 'tag/'.Util::encryptTopic("group$id");
                    }
                }
                if ($all['tags']) {
                    foreach ($all['tags'] as $id) {
                        $topics[] = 'tag/'.Util::encryptTopic("tag$id");
                    }
                }
                if ($all['devices']) {
                    foreach ($all['devices'] as $id) {
                        $topics[] = 'tag/'.Util::encryptTopic("device$id");
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
     * 创建一个延时回调任务
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

    /**
     * 与控制中心交互 api版本v1
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
     */
    public static function queryData(
        $version,
        $path,
        array $params = [],
        $body = '',
        string $content_type = '',
        string $method = ''
    ) {
        if (!self::$http_client) {
            return err('没有配置请求对象！');
        }

        $api_url = self::$default_config['url'];
        $app_key = self::$default_config['appKey'];
        $app_secret = self::$default_config['appSecret'];

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
                if (empty($content_type)) {
                    $content_type = 'application/x-www-form-urlencoded';

                }
                if ($content_type == 'application/x-www-form-urlencoded') {
                    parse_str($body, $result);
                    $params = array_merge($params, $result);
                }
            } else {
                $content_type = 'application/json';
                $body = json_encode($body);
            }

            if ($content_type) {
                $headers['Content-Type'] = $content_type;
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

    public static function makeNotifierSign($app_key, $app_secret, $nostr, $payload): string
    {
        return hash_hmac("sha256", "$app_key$nostr$payload", $app_secret);
    }

    /**
     * 生成控制中心通信签名
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
     */
    public static function makeCtrlServerSign($app_key, $app_secret, string $nostr = TIMESTAMP): string
    {
        return hash_hmac("sha256", "$app_key$nostr", $app_secret);
    }

    /**
     * 检查job参数的签名
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
     */
    public static function makeSign(array $params = []): string
    {
        ksort($params);

        return sha1(http_build_query($params).self::$default_config['signature']);
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
}
