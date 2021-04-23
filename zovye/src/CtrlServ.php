<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\Contract\IHttpClient;
use zovye\model\advertisingModelObj;

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
        return hash_hmac("sha256", "{$app_key}{$nostr}{$payload}", $app_secret);
    }

    /**
     * 通知设备mcb更新设置
     * @param $mcbUID
     * @param $code
     * @param string $op
     * @param array $payloadData
     * @return bool
     */
    public static function mcbNotify($mcbUID, $code, $op = 'params', $payloadData = []): bool
    {
        if ($mcbUID) {

            $topic = ["v1/device/mcb/{$mcbUID}/{$code}"];
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
     * @param $path
     * @param array $params
     * @param string $body
     * @param string $contentType
     * @param string $method
     * @return mixed
     */
    public static function query($path = '', array $params = [], $body = '', $contentType = '', $method = '')
    {
        return self::queryData('v1', $path, $params, $body, $contentType, $method);
    }

    /**
     * 与控制中心交互
     * @param $version
     * @param $path
     * @param array $params
     * @param string $body
     * @param string $contentType
     * @param string $method
     * @return mixed
     */
    public static function queryData($version, $path, array $params = [], $body = '', $contentType = '', $method = '')
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
            $ctrlServerUrl .= "/{$path}";

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

            $headers['zovye-key'] = $appKey;
            $headers['zovye-sign'] = self::makeCtrlServerSign(settings('ctrl.appKey', ''), settings('ctrl.appSecret', ''), $params['nostr']);

            return self::$http_client->request($ctrlServerUrl, $method, $headers, $body);
        }

        return error(State::ERROR, '没有配置请求对象！');
    }

    /**
     * 生成控制中心通信签名
     * @param $app_key
     * @param $app_secret
     * @param $nostr
     * @return string
     */
    public static function makeCtrlServerSign($app_key, $app_secret, $nostr = TIMESTAMP): string
    {
        return hash_hmac("sha256", "{$app_key}{$nostr}", $app_secret);
    }

    /**
     * 与控制中心交互 api版本v2
     * @param $path
     * @param array $params
     * @param string $body
     * @param string $contentType
     * @param string $method
     * @return mixed
     */
    public static function v2_query($path, array $params = [], $body = '', $contentType = '', $method = '')
    {
        return self::queryData('v2', $path, $params, $body, $contentType, $method);
    }

    /**
     * 通知设备app更新设置
     * @param $app_id
     * @param string $op
     * @param array $payloadData
     * @return bool
     */
    public static function appNotify($app_id, $op = 'update', $payloadData = []): bool
    {
        if ($app_id) {

            $topic = ["app/{$app_id}"];
            $data = json_encode(
                [
                    'op' => $op,
                    'data' => $payloadData,
                    'serial' => microtime(true) . '',
                ]
            );

            $body = json_encode(['topics' => $topic, 'data' => $data]);

            $res = self::query('misc/publish', ['nostr' => sha1($body),], $body, 'application/json');

            return !is_error($res);
        }

        return false;
    }

    /**
     * 通知相关终端更新广告
     * @param array $origin_data 原始分配信息
     * @param array $data 新的分配信息
     * @param advertisingModelObj $adv 广告对象
     * @return boolean
     *  [
     *  'all' => true/false,    //全部设备
     *  'agents' => [],         //代理商
     *  'groups' => [],         //分组
     *  'tags' => [],           //标签
     *  'devices' => [],        //设备
     *  );
     */

    public static function advsNotifyAll(array $origin_data, array $data = [], $adv = null): bool
    {
        $topics = [];

        if ($data['all']) {
            if (!$origin_data['all']) {
                $topics[] = 'tag/' . Topic::encrypt();
            }
        } else {
            if ($origin_data['all']) {
                $topics[] = 'tag/' . Topic::encrypt();
            } else {
                if (isset($data['all']) && $data['all'] == false) {
                    $all = $origin_data;
                } else {
                    $all = $data;
                    foreach ($origin_data as $key => $entry) {
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
                        $topics[] = 'tag/' . Topic::encrypt("agent{$id}");
                    }
                }
                if ($all['groups']) {
                    foreach ($all['groups'] as $id) {
                        $topics[] = 'tag/' . Topic::encrypt("group{$id}");
                    }
                }
                if ($all['tags']) {
                    foreach ($all['tags'] as $id) {
                        $topics[] = 'tag/' . Topic::encrypt("tag{$id}");
                    }
                }
                if ($all['devices']) {
                    foreach ($all['devices'] as $id) {
                        $topics[] = 'tag/' . Topic::encrypt("device{$id}");
                    }
                }
            }
        }

        if ($topics) {

            $serial = microtime(true);
            $content = json_encode(['op' => 'update', 'serial' => "{$serial}"]);
            $body = ['topics' => $topics, 'data' => $content];

            $res = self::query('misc/publish', [], $body);
            if (!is_error($res)) {
                if ($adv) {
                    $adv->set(
                        'lastupdate',
                        [
                            'notifier' => [
                                'topics' => $topics,
                                'data' => $content,
                            ],
                            'ts' => time(),
                            'serial' => $serial,
                        ]
                    );
                }

                return true;
            }

            return false;
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
    public static function scheduleDelayJob($op, array $params = [], $delay = 0): bool
    {
        $result = self::httpDelayCallback($delay, self::makeJobUrl($op, $params));
        return !is_error($result) && $result !== false;
    }

    /**
     * 加入一个延时回调任务
     * @param $delay
     * @param $url
     * @param string $data
     * @return mixed
     */
    public static function httpDelayCallback($delay, $url, $data = '')
    {
        $uid = Util::random(16);
        $query = [
            'delay' => intval($delay),
            'url' => $url,
            'data' => $data,
        ];

        return self::query("job/delay/{$uid}", [], http_build_query($query));
    }

    /**
     * 创建一个优先级回调任务
     * @param $op
     * @param array $params
     * @param string $level
     * @return mixed
     */
    public static function scheduleJob($op, array $params = [], $level = LEVEL_NORMAL): bool
    {
        $result = self::httpQueuedCallback($level, self::makeJobUrl($op, $params));
        return !is_error($result) && $result !== false;
    }

    /**
     * 在队列中加入一个回调任务
     * @param $level
     * @param $url
     * @param string $data
     * @return mixed
     */
    public static function httpQueuedCallback($level, $url, $data = '')
    {
        $query = [
            'type' => $level,
            'url' => $url,
            'data' => $data,
        ];

        $uid = Util::random(16, true);

        return self::query("job/queue/{$uid}", [], http_build_query($query));
    }
}
