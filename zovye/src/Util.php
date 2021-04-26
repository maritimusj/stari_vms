<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use ali\aop\AopClient;
use ali\aop\request\AlipaySystemOauthTokenRequest;
use ali\aop\request\AlipayUserInfoShareRequest;
use DateTime;
use Exception;
use QRcode;
use RuntimeException;
use Throwable;
use we7\ihttp;
use zovye\base\modelObj;
use zovye\model\accountModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\agentModelObj;
use zovye\model\goods_voucher_logsModelObj;

class Util
{
    public static function config($sub = '')
    {
        static $config = null;
        if (!isset($config)) {
            $config = require_once(ZOVYE_CORE_ROOT . 'config.php');
        }
        return getArray($config, $sub);
    }

    /**
     * 检查指定trait是否可用.
     *
     * @param mixed $class 要检查的类
     * @param string $traitName trait名称
     *
     * @return bool
     */
    public static function traitUsed($class, string $traitName): bool
    {
        $traits = class_uses($class);
        foreach (class_parents($class) as $classname) {
            $traits = array_merge($traits, class_uses($classname));
        }

        return $traits && in_array(__NAMESPACE__ . '\\traits\\' . $traitName, $traits);
    }

    /**
     * 返回一个表结构描述.
     *
     * @param string $tab_name
     *
     * @return array
     */
    public static function tableSchema(string $tab_name): array
    {
        $ret = [];
        $db = We7::pdo();
        if ($db->tableexists($tab_name)) {
            $result = $db->fetchall('SHOW FULL COLUMNS FROM ' . $db->tablename($tab_name));
            foreach ($result as $value) {
                $temp = [];
                $type = explode(' ', $value['Type'], 2);
                $temp['name'] = $value['Field'];
                $pieces = explode('(', $type[0], 2);
                if ($temp) {
                    $temp['type'] = $pieces[0];
                    $temp['length'] = rtrim($pieces[1], ')');
                }
                $temp['null'] = $value['Null'] != 'NO';
                //暂时去掉默认值的对比
                //if(isset($value['Default'])) {
                //    $temp['default'] = $value['Default'];
                //}
                $temp['signed'] = empty($type[1]);
                $temp['increment'] = $value['Extra'] == 'auto_increment';
                $ret['fields'][$value['Field']] = $temp;
            }
        }

        return $ret;
    }


    /**
     * 获取当前用户信息.
     *
     * @param array $params
     *
     * @return userModelObj|null
     */
    public static function getCurrentUser(array $params = []): ?userModelObj
    {
        $user = null;
        if (App::isAliUser()) {
            $user = User::get(App::getUserUID(), true);
        } else {
            if (empty($user)) {
                $fans = Util::fansInfo();
                if ($fans && !empty($fans['openid'])) {
                    $user = User::get($fans['openid'], true);

                    $update = empty($params['update']) ? false : true;
                    if (empty($user) && !empty($params['create'])) {
                        $data = [
                            'app' => User::WX,
                            'nickname' => $fans['nickname'],
                            'avatar' => $fans['headimgurl'],
                            'openid' => $fans['openid'],
                        ];

                        $user = User::create($data);
                        if ($user) {
                            //创建云众商城关联
                            if (isset($params['yzshop']) && YZShop::isInstalled()) {
                                $yz_shop = $params['yzshop'];
                                $agent = null;
                                if (isset($yz_shop['agent']) && $yz_shop['agent'] instanceof userModelObj) {
                                    $agent = $yz_shop['agent'];
                                } elseif (isset($yz_shop['device']) && $yz_shop['device'] instanceof deviceModelObj) {
                                    $agent = $yz_shop['device']->getAgent();
                                }

                                if ($agent) {
                                    YZShop::create($user, $agent);
                                }
                            }

                            if (!empty($params['from'])) {
                                $user->set('fromData', $params['from']);
                            }
                            $user->set('fansData', $fans);
                            if (isset($params['update'])) {
                                $update = false;
                            }
                        }
                    }

                    if ($user) {
                        if ($update) {
                            if ($user->getNickname() != $fans['nickname']) {
                                $user->setNickname($fans['nickname']);
                            }
                            if ($user->getAvatar() != $fans['headimgurl']) {
                                $user->setAvatar($fans['headimgurl']);
                            }

                            $user->set('fansData', $fans);
                            $user->save();
                        }
                    }
                }
            }
        }
        return $user;
    }


    /**
     * 获取fans数据.
     *
     * @return array
     */
    public static function fansInfo(): array
    {
        if (_W('openid')) {
            $res = We7::mc_oauth_userinfo();
            if (!is_error($res)) {
                return $res;
            }
        }

        return [];
    }

    public static function getAliUser(string $code, deviceModelObj $device = null): ?userModelObj
    {
        try {
            $aop = new AopClient();
            $aop->appId = settings('ali.appid');
            $aop->rsaPrivateKey = settings('ali.prikey');
            $aop->alipayrsaPublicKey = settings('ali.pubkey');

            $request = new AlipaySystemOauthTokenRequest();
            $request->setGrantType('authorization_code');
            $request->setCode($code);


            $result = $aop->execute($request);

            if ($result->error_response) {
                throw new RuntimeException('获取用户信息失败：' . $result->error_response->sub_msg);
            }

            //access_token;
            $access_token = $result->alipay_system_oauth_token_response->access_token;

            //获取用户信息
            $request = new AlipayUserInfoShareRequest();
            $result = $aop->execute($request, $access_token);

            if ($result->alipay_user_info_share_response->code !== '10000') {
                throw new RuntimeException('获取用户信息失败：' . $result->alipay_user_info_share_response->sub_msg);
            }

            $ali_user_id = $result->alipay_user_info_share_response->user_id;
            $nick_name = $result->alipay_user_info_share_response->nick_name;
            $avatar = $result->alipay_user_info_share_response->avatar;

            $user = User::get($ali_user_id, true, User::ALI);
            if (empty($user)) {
                $data = [
                    'app' => User::ALI,
                    'nickname' => $nick_name,
                    'avatar' => $avatar,
                    'openid' => $ali_user_id,
                ];

                $user = User::create($data);
                if (!empty($device)) {
                    $params['from'] = [
                        'src' => 'device',
                        'device' => [
                            'name' => $device->getName(),
                            'imei' => $device->getImei(),
                        ],
                        'ip' => CLIENT_IP,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    ];

                    $user->set('fromData', $params['from']);
                    $user->save();
                }
            } else {
                $user->setNickname($nick_name);
                $user->setAvatar($avatar);
                $user->save();
            }
            return $user;
        } catch (Exception $e) {
            Util::logToFile('error', [
                'msg' => '获取阿里用户身份失败！',
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 创建并返回日志目录
     * @param string $name
     * @return string
     */
    public static function logDir(string $name): string
    {
        $log_dir = LOG_DIR . App::uid(8) . DIRECTORY_SEPARATOR . $name;

        We7::mkDirs($log_dir);

        return $log_dir;
    }

    /**
     * 返回日志文件名
     * @param string $name
     * @return string
     */
    public static function logFileName(string $name): string
    {
        $log_dir = self::logDir($name);
        return $log_dir . DIRECTORY_SEPARATOR . date('Ymd') . '.log';
    }

    /**
     * 输出指定变量到文件中
     * @param string $name 日志名称
     * @param mixed $data 数据
     * @return bool
     */
    public static function logToFile(string $name, $data): bool
    {
        static $cache = [];

        $log_filename = self::logFileName($name);   

        ob_start();

        echo PHP_EOL . "-----------------------------" . date('Y-m-d H:i:s') . ' [ ' . REQUEST_ID . " ]---------------------------------------" . PHP_EOL;

        print_r($data);

        echo PHP_EOL;

        $cache[$log_filename][] = ob_get_clean();

        register_shutdown_function(function() use($cache) {
            foreach($cache as $filename => $data) {
                if ($filename && $data) {
                    file_put_contents($filename, $data, FILE_APPEND);
                }
            }
        });

        return true;
    }

    public static function setErrorHandler()
    {
        if (DEBUG) {
            error_reporting(E_ALL ^ E_NOTICE);
        } else {
            error_reporting(0);
        }

        set_error_handler(function ($severity, $str, $file, $line) {
            Util::logToFile('error', [
                'level' => $severity,
                'str' => $str,
                'file' => $file,
                'line' => $line,
            ]);
        }, E_ALL ^ E_NOTICE);

        set_exception_handler(function (Throwable $e) {
            Util::logToFile('error', [
                'type' => get_class($e),
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    }

    public static function createApiRedirectFile(string $filename, string $do, array $params = [], callable $fn = null)
    {
        We7::mkDirs(dirname(ZOVYE_ROOT . $filename));
        
        $headers = is_array($params['headers']) ? $params['headers'] : [];
        unset($params['headers']);

        if (empty($headers['HTTP_USER_AGENT'])) {
            $headers['HTTP_USER_AGENT'] = 'api_redirect';
        }
        if (empty($headers['HTTP_X_REQUESTED_WITH'])) {
            $headers['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        }

        $header_str = '';
        foreach ($headers as $name => $val) {
            $header_str .= "\$_SERVER['{$name}'] = '{$val}';\r\n";
        }

        $memo = !empty($params['memo']) ? strval($params['memo']) : 'API转发程序';
        unset($params['memo']);

        if ($do) {
            $params['do'] = $do;
        }

        $appName = APP_NAME;
        $uniacid = We7::uniacid();
        $appPath = realpath(ZOVYE_ROOT . '../../app');

        $content = "<?php
/**
 * {$memo}
 *
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

{$header_str}
\$_GET['m'] = '{$appName}';
\$_GET['i'] = {$uniacid};
\$_GET['c'] = 'entry';
";
        foreach ($params as $name => $val) {
            $content .= "\$_GET['{$name}'] = '{$val}';\r\n";
        }

        if ($fn) {
            $content .= $fn();
        }

        $content .= "
chdir('{$appPath}');
include './index.php';
";
        return file_put_contents(ZOVYE_ROOT . $filename, $content);
    }

    /**
     * 缓存指定函数的调用结果，在指定时间内在重复调用
     * @param $interval_seconds
     * @param callable $fn
     * @param mixed ...$params 用来同一个函数应用了不同的参数的情况
     * @return mixed
     */
    public static function cachedCall($interval_seconds, callable $fn, ...$params)
    {
        $key = App::uid(6) . hashFN($fn, ...$params);

        $last = We7::cache_read($key);
        if ($last && is_array($last) && time() - intval($last['time']) < $interval_seconds) {
            return $last['v'];
        }

        $result = $fn();

        We7::cache_write($key, [
            'time' => time(),
            'v' => $result,
        ]);

        return $result;
    }

    public static function isAssigned($data, deviceModelObj $device): bool
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        if ($data['all']) {
            return true;
        }

        if ($data['agents']) {
            $agent = $device->getAgent();
            if ($agent && in_array($agent->getId(), $data['agents'])) {
                return true;
            }
        }

        if ($data['groups']) {
            $group_id = $device->getGroupId();
            if ($group_id && in_array($group_id, $data['groups'])) {
                return true;
            }
        }

        if ($data['tags']) {
            $tags = $device->getTagsAsId();
            if ($tags && array_intersect($data['tags'], $tags)) {
                return true;
            }
        }

        if ($data['devices']) {
            if (in_array($device->getId(), $data['devices'])) {
                return true;
            }
        }

        return false;
    }


    /**
     * 判断用户在指定公众号以及指定设备是否还有免费额度.
     *
     * @param mixed $user 用户openid或者用户对象
     * @param mixed $account 公众号名称或者对象
     * @param deviceModelObj $device 设备对象
     * @param array $params 更多条件
     *
     * @return bool|array
     */
    public static function isAvailable($user, accountModelObj $account, deviceModelObj $device, array $params = [])
    {
        //每日免费额度限制
        if (empty(Util::getUserTodayFreeNum($user, $device))) {
            return error(State::ERROR, '今天领的太多了，明天再来！');
        }

        $assign_data = $account->settings('assigned', []);
        if (!Util::isAssigned($assign_data, $device)) {
            return error(State::ERROR, '没有允许从这个设备访问该公众号！');
        }

        $sc_name = $account->getScname();
        $total = intval($account->getTotal());
        $count = intval($account->getCount());
        $sc_count = intval($account->getSccount());
        $order_limits = intval($account->getOrderLimits());

        //检查性别，手机限制
        $limits = $account->get('limits');
        if (is_array($limits)) {
            $limit_fn = [
                'male' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 1) {
                        return error(State::FAIL, '不允许男性用户');
                    }

                    return true;
                },
                'female' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 2) {
                        return error(State::FAIL, '不允许女性用户');
                    }

                    return true;
                },
                'unknown_sex' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 0) {
                        return error(State::FAIL, '不允许未知性别用户');
                    }

                    return true;
                },
                'ios' => function ($val) {
                    if ($val == 0 && Util::getUserPhoneOS() == 'ios') {
                        return error(State::FAIL, '不允许ios手机');
                    }

                    return true;
                },
                'android' => function ($val) {
                    if ($val == 0) {
                        $os = Util::getUserPhoneOS();
                        if ($os == 'android' || $os == 'unknown') {
                            return error(State::FAIL, '不允许android手机');
                        }
                    }

                    return true;
                },
            ];

            foreach ($limits as $item => $val) {
                if ($val == 0 && $limit_fn[$item]) {
                    $fn = $limit_fn[$item];
                    $res = $fn($val);

                    if (is_error($res)) {
                        return $res;
                    }
                }
            }
        }

        $name = $account->getName();
        $condition = [
            'account' => $name,
            'openid' => $user->getOpenid(),
        ];

        if ($params['unfollow'] || in_array('unfollow', $params, true)) {
            if (Order::query()->exists($condition)) {
                return error(State::ERROR, '你已经关注过这个公众号！');
            }
        }

        $time = 0;
        if ($name && Schema::has($sc_name)) {
            if ($sc_name == Schema::DAY) {
                $time = strtotime('today');
            } elseif ($sc_name == Schema::WEEK) {
                $time = date('D') == 'Mon' ? strtotime('today') : strtotime('last Mon');
            } elseif ($sc_name == Schema::MONTH) {
                $time = strtotime(date('Y-m'));
            }

            //count，单个用户在每个周期内可领取数量
            if ($count > 0) {
                $query = Order::query($condition);
                $query->where(['createtime >=' => $time, 'createtime <' => time()]);
                if ($query->limit($count + 1)->get('sum(num)') >= $count) {
                    $desc = [
                        Schema::DAY => '您今天已经领过了，明天再来吧！',
                        Schema::WEEK => '下个星期再来试试吧',
                        Schema::MONTH => '这个月的免费额度已经用完啦！',
                    ];
                    return error(State::ERROR, $desc[$sc_name]);
                }
            }

            //scCount, 所有用户在每个周期内总数量
            if ($sc_count > 0) {
                $query = Order::query(['account' => $name]);
                $query->where(['createtime >=' => $time, 'createtime <' => time()]);
                if ($query->limit($sc_count + 1)->get('sum(num)') >= $sc_count) {
                    return error(State::ERROR, '这个公众号暂时不能领取！');
                }
            }

            //total，单个用户累计可领取数量
            if ($total > 0) {
                $query = Order::query($condition);
                if ($query->limit($total + 1)->get('sum(num)') >= $total) {
                    return error(State::ERROR, '这个公众号的免费额度已经用完！');
                }
            }

            //$orderLimits，公众号最大订单数量
            if ($order_limits > 0) {
                $query = Order::query(['account' => $name]);
                if ($query->limit($order_limits + 1)->get('sum(num)') >= $order_limits) {
                    return error(State::ERROR, '公众号的免费数量已经超出限制！');
                }
            }

            return true;
        }

        return error(State::ERROR, '您的免费额度已经用完！');
    }

    /**
     * 获取用户今日免费可领取的数量.
     *
     * @param userModelObj $user
     * @param deviceModelObj $device
     *
     * @return int|mixed|null
     */
    public static function getUserTodayFreeNum(userModelObj $user, deviceModelObj $device): int
    {
        $remain = null;

        if (is_null($remain)) {
            $max_free = 0;

            $agent = $device->getAgent();
            if ($agent) {
                $agent_data = $agent->getAgentData();
                $max_free = intval($agent_data['misc']['maxFree']);
            }

            $max_free = $max_free > 0 ? $max_free : (int)settings('user.maxFree', 0);

            if ($max_free > 0) {
                $remain = max(0, $max_free - $user->getTodayFreeTotal());
            } else {
                $remain = 1;
            }
        }

        return $remain;
    }

    /**
     * 简单获取用户手机系统类型.
     *
     * @return string
     */
    public static function getUserPhoneOS(): string
    {
        if (stripos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false) {
            return 'ios';
        }

        if (stripos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false) {
            return 'android';
        }

        return 'unknown';
    }

    /**
     * 订单统计
     *
     * @param orderModelObj $order 订单对象
     *
     * @return array
     */
    public static function orderStatistics(orderModelObj $order): array
    {
        $result = [];

        $locker = Util::lockObject($order, ['updatetime' => 0]);

        if ($locker && $locker->isLocked()) {
            //更新统计
            $stats_objs = [app()];

            $device = $order->getDevice();
            if ($device) {
                $stats_objs[] = $device;
            }

            $agent = $order->getAgent();
            if ($agent) {
                $stats_objs[] = $agent;
            }

            $goods = $order->getGoods();
            if ($goods) {
                $stats_objs[] = $goods;
            }

            Stats::update($order, $stats_objs);

            $name = $order->getAccount();
            if ($name) {
                $account = Account::findOne(['name' => $name]);
                if ($account) {
                    $order_limits = $account->getOrderLimits();

                    //更新公众号统计，并检查吸粉总量
                    Stats::update(
                        $order,
                        $account,
                        function ($entry, $stats) use ($order_limits, $account, &$result) {
                            unset($entry);
                            if ($order_limits > 0) {
                                $total = $stats['total']['p'] + $stats['total']['b'] + $stats['total']['f'];
                                if ($total >= $order_limits) {
                                    $account->setState(Account::BANNED);
                                    Account::updateAccountData();

                                    $result['account.banned'] = [
                                        'title' => $account->getTitle(),
                                        'total' => $total,
                                    ];
                                }
                            }
                        }
                    );
                }
            }
        } else {
            $result[] = $order->getId() . ' lock failed!';
        }

        return $result;
    }


    /**
     * 通过写入唯一值，锁定数据库中某一行数据，成功返回锁对象，失败返回null.
     *
     * @param modelObj $obj 数据对象，必须是modelObj子类
     * @param array $cond 条件数组，用于判断是否可以锁定对象
     * @param bool $auto_unlock 是否自动解锁
     *
     * @return ?RowLocker
     */
    public static function lockObject(modelObj $obj, array $cond, $auto_unlock = false): ?RowLocker
    {
        $seg = key($cond);
        if (is_string($seg)) {
            $val = $cond[$seg];
            $condition = [
                'id' => $obj->getId(),
                $seg => $val,
            ];

            $locker = new RowLocker($obj->getTableName($obj::OP_WRITE), $condition, $seg, $auto_unlock);
            if ($locker->isLocked()) {
                return $locker;
            }
        }

        return null;
    }

    /**
     * @param $msg
     * @param string $redirect
     * @param string $type
     */
    public static function message($msg, $redirect = '', $type = '')
    {
        We7::message($msg, $redirect, $type);
    }

    /**
     * @param $msg
     * @param string $redirect
     * @param string $type
     */
    public static function itoast($msg, $redirect = '', $type = '')
    {
        We7::itoast($msg, $redirect, $type);
    }

    /**
     * 返回JSON响应.
     *
     * @param bool $status 结果
     * @param mixed $data 数据
     */
    public static function resultJSON(bool $status, $data = [])
    {
        header('Content-type: application/json; charset=' . _W('charset'));

        if (request::has('callback')) {
            echo request('callback') . '(' . json_encode(['status' => $status, 'data' => $data]) . ')';
        } else {
            echo json_encode(['status' => $status, 'data' => $data]);
        }

        exit();
    }

    /**
     * 手机端显示错误信息.
     *
     * @param string $msg
     * @param string $type
     * @param string $redirect
     */
    public static function resultAlert(string $msg, string $type = 'success', string $redirect = '')
    {
        if (in_array($type, ['ok', 'success'])) {
            $icon = 'success';
            $btn = 'success';
        } else {
            $icon = 'error';
            $btn = 'danger';
        }

        if (_W('container') == 'wechat') {
            $jssdk = Util::fetchJSSDK(false);
            $js = <<<JS1
{$jssdk}
<script type="text/javascript">
    const url = "{$redirect}";
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });
    function xclose(){
        if(url) {
            location.href = url;
        }else{
            wx && wx.closeWindow();
        }
    }
</script>
JS1;
        } else {
            $js = <<<JS2
<script type="text/javascript">
    const url = "{$redirect}";
    function xclose(){
        if(url) {
            location.href = url;
        }
    }
</script>
JS2;
        }

        $css_url = _W('siteroot') . 'app/resource/css/common.min.css?v=20160906';
        $content = <<<HTML_CONTENT
<!DOCTYPE html>
<html lang="zh-hans">
	<head>
		<meta charset="UTF-8">
		<title>提示</title>
		<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
    	<meta name="format-detection" content="telephone=no, address=no">
    	<meta name="apple-mobile-web-app-capable" content="yes" /> <!-- apple devices fullscreen -->
    	<meta name="apple-touch-fullscreen" content="yes"/>
    	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    	<link href="{$css_url}" rel="stylesheet">
	</head>
    <body>
        <div class="mui-content">
		    <div class="mui-content-padded">
	        <div class="mui-message">
    			<div class="mui-message-icon">
    				<span class="mui-msg-{$icon}"></span>
    			</div>
    			<h4 class="title">{$msg}</h4>
    			<div class="mui-button-area">
    				<button type="button" class="mui-btn mui-btn-{$btn} mui-btn-block" onClick="xclose()">确定</button>
    			</div>
		    </div>
		</div>

    {$js}
    </div>
    </body>
</html>
HTML_CONTENT;

        exit($content);
    }

    /**
     * 获取返回jssdk字符串.
     *
     * @param bool $debug
     *
     * @return string
     */
    public static function fetchJSSDK($debug = false): string
    {
        ob_start();
        We7::register_jssdk($debug);
        return ob_get_clean();
    }

    /**
     * 获取需要通知的openids.
     *
     * @param agentModelObj $agent
     * @param string $type
     *
     * @return array
     */
    public static function getNotifyOpenids(agentModelObj $agent, string $type): array
    {
        $result = [];

        if ($agent && $type) {
            $agent_data = $agent->getAgentData();
            if ($agent_data['notice'][$type]) {
                $result[$agent->getId()] = $agent->getOpenid();
            }

            foreach ($agent_data['partners'] ?: [] as $partner_id => $data) {
                if ($data['notice'][$type]) {
                    $result[$partner_id] = $data['openid'];
                }
            }
        }

        return $result;
    }

    /**
     * 获取控制服务器回调网址
     *
     * @param array $params
     *
     * @return mixed
     */
    public static function getCtrlServCallbackUrl(array $params = []): string
    {
        $params = array_merge(
            [
                'm' => APP_NAME,
                'sign' => settings('ctrl.signature'),
            ],
            $params
        );

        return Util::murl('ctrl', $params);
    }

    /**
     * 将ajax请求中的的json数据合并到$GLOBALS['_GPC']中.
     */
    public static function extraAjaxJsonData()
    {
        if (stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $data = @json_decode($input, true);
                if (!empty($data)) {
                    $GLOBALS['_GPC'] = array_merge($GLOBALS['_GPC'], $data);
                    setW('isajax', true);
                }
            }
        }
    }

    /**
     * 查找指定对象
     *
     * @param $tb
     * @param $val
     * @param null $hint
     * @param null $must
     *
     * @return mixed
     */
    public static function findObject($tb, $val, $hint = null, $must = null)
    {
        if ($tb && $val) {
            $must_cond_fn = function ($cond = []) use ($must) {
                $cond = is_array($cond) ? $cond : [$cond];
                $cond['uniacid'] = We7::uniacid();
                if ($must) {
                    if (is_array($must)) {
                        $cond = array_merge($cond, $must);
                    } elseif (is_string($must)) {
                        $cond[] = $must;
                    }
                }
                foreach ($cond as $key => $val) {
                    if (empty($val)) {
                        unset($cond[$key]);
                    }
                }

                return $cond;
            };

            $query = m($tb)->query();
            if (empty($hint)) {
                if (is_scalar($val)) {
                    if (is_numeric($val)) {
                        $query->where($must_cond_fn(['id' => intval($val)]));
                    } elseif (is_string($val)) {
                        $query->where($must_cond_fn())->where($val);
                    }
                } elseif (is_array($val)) {
                    $query->where($must_cond_fn());
                    foreach ($val as $key => $entry) {
                        if ($entry) {
                            if (is_numeric($key)) {
                                $query->where($entry);
                            } elseif ($key) {
                                $query->where([$key => $entry]);
                            }
                        }
                    }
                }
            } elseif (is_scalar($hint)) {
                if (is_scalar($val)) {
                    $query->where($must_cond_fn([$hint => $val]));
                } elseif (is_array($val)) {
                    $query->where($must_cond_fn([]));
                    foreach ($val as $entry) {
                        $query->whereOr([$hint => $entry]);
                    }
                }
            } elseif (is_array($hint)) {
                if (is_scalar($val)) {
                    $query->where($must_cond_fn([]));
                    foreach ($hint as $key) {
                        if ($key) {
                            $query->whereOr([$key => $val]);
                        }
                    }
                }
            }

            return $query->limit(1)->findAll()->current();
        }

        return null;
    }

    /**
     * 获取ip地址定位信息.
     *
     * @param $ip
     *
     * @return mixed
     */
    public static function getIpInfo($ip): string
    {
        $lbs_key = settings('user.location.appkey', DEFAULT_LBS_KEY);
        $url = 'https://apis.map.qq.com/ws/location/v1/ip';
        $params = urlencode("?ip={$ip}&key={$lbs_key}");

        $resp = ihttp::get($url . $params);

        if (!is_error($resp)) {
            $res = json_decode($resp['content'], true);

            if ($res && $res['status'] == 0 && is_array($res['result'])) {
                $data = $res['result'];
                $data['data'] = [
                    'region' => $data['ad_info']['province'],
                    'city' => $data['ad_info']['city'],
                    'district' => $data['ad_info']['district'],
                ];

                return json_encode($data);
            }
        }

        return '';
    }

    /**
     * 格式化时间.
     *
     * @param $ts
     *
     * @return string
     *
     * @throws
     */
    public static function getFormattedPeroid($ts): string
    {
        $time = (new DateTime())->setTimestamp($ts);
        $interval = (new DateTime())->diff($time);
        if ($interval->y) {
            return $interval->format('%y年%m月%d天%h小时%i分钟%S秒');
        }
        if ($interval->m) {
            return $interval->format('%m月%d天%h小时%i分钟%S秒');
        }
        if ($interval->d) {
            return $interval->format('%d天%h小时%i分钟%S秒');
        }
        if ($interval->h) {
            return $interval->format('%h小时%i分钟%S秒');
        }
        if ($interval->i) {
            return $interval->format('%i分钟%S秒');
        }

        return $interval->format('%S秒');
    }

    /**
     * 修正微擎payresult回调时，tomedia函数工作不正常的问题.
     *
     * @param $src
     * @param bool $local_path
     *
     * @return mixed
     */
    public static function toMedia($src, $use_image_proxy = false, $local_path = false)
    {
        if (empty(_W('attachurl'))) {
            We7::load()->model('attachment');
            setW('attachurl', We7::attachment_set_attach_url());
        }
        $res = We7::tomedia($src, $local_path);
        if (!$local_path) {
            $str = ['/addons/' . APP_NAME];
            $replacements = [''];
            if (App::isHttpsWebsite()) {
                $str[] = 'http://';
                $replacements[] = 'https://';
            }
            $res = str_replace($str, $replacements, $res);
        }

        return $use_image_proxy ? Util::getImageProxyURL($res) : $res;
    }

    public static function getAttachmentFileName(string $dirname, string $filename): string
    {
        $full_path = ATTACHMENT_ROOT . $dirname . $filename;

        if (!is_dir(ATTACHMENT_ROOT . $dirname)) {
            We7::mkDirs(ATTACHMENT_ROOT . $dirname);
        }

        return $full_path;
    }

    /**
     * 创建二维码 $id = type.uid形式指定.
     *
     * @param $id
     * @param $text
     * @param callable|null $cb
     * @return string|array
     */
    public static function createQrcodeFile($id, $text, callable $cb = null)
    {
        if (stripos($id, '.') !== false) {
            list($type, $id) = explode('.', $id, 2);
            if (empty($type)) {
                $type = 'default';
            }
            if (empty($id)) {
                $id = sha1($text);
            }
        } else {
            $type = 'default';
        }

        $filename = "{$id}.png";
        $dirname = "zovye/{$type}/";

        $full_filename = self::getAttachmentFileName($dirname, $filename);

        load()->library('qrcode');

        $error_correction_level = 'L';
        $matrix_point_size = '8';

        QRcode::png($text, $full_filename, $error_correction_level, $matrix_point_size);

        if (file_exists($full_filename)) {
            if ($cb != null ) {
                $cb($full_filename);
            }

            try {
                We7::file_remote_upload("{$dirname}{$filename}");
            } catch (Exception $e) {
                self::logToFile('createQrcodeFile', $e->getMessage());
            }

            return "{$dirname}{$filename}";
        }

        return error(State::ERROR, '创建文件失败！');
    }

    public static function download($url, $dirname, $filename): string
    {
        $content = Util::get($url);
        if ($content !== null) {
            if (stripos($filename, '{hash}') !== false) {
                $filename = str_replace('{hash}', sha1($content), $filename);
            }
            $full_filename = self::getAttachmentFileName($dirname, $filename);
            if (file_put_contents($full_filename, $content) !== false) {
                return "{$dirname}{$filename}";
            }
        }

        return 'error';
    }

    public static function downloadQRCode($url): string
    {
        return self::download($url, 'download/qrcode/', '{hash}.png');
    }

    /**
     * 返回设备错误描述.
     *
     * @param $code
     *
     * @return string
     */
    public static function descDevice($code): string
    {
        if (Device::has($code)) {
            return Device::desc($code);
        }

        return "未知故障，代码[{$code}]";
    }

    /**
     * 发送短信
     *
     * @param $mobile
     * @param $tpl_id
     * @param $msg
     *
     * @return bool|array
     */
    public static function sendSMS($mobile, $tpl_id, $msg)
    {
        $config = settings('notice.sms', []);

        if ($config['url'] && $config['appkey']) {
            $tpl_value = '';

            if (is_string($msg)) {
                $tpl_value = $msg;
            } elseif (is_array($msg)) {
                $arr = [];
                foreach ($msg as $key => $value) {
                    $arr[] = "#{$key}#=" . urlencode($value);
                }

                $tpl_value = implode('&', $arr);
            }

            $res = ihttp::post($config['url'], [
                'mobile' => $mobile,
                'tpl_id' => $tpl_id,
                'tpl_value' => urlencode($tpl_value),
                'key' => $config['appkey'],
            ]);

            if ($res['code'] == 200) {
                $result = json_decode($res['content'], true);
                if ($result['error_code'] === 0) {
                    return true;
                }

                return error(State::ERROR, $result['reason']);
            }
        }

        return error(State::ERROR, '请先配置短信接口！');
    }

    /**
     * @param userModelObj|keeperModelObj $user
     * @param int|string|deviceModelObj $device
     * @param int $lane
     * @param array $params
     *
     * @return array
     */
    public static function deviceTest($user, $device, $lane = Device::DEFAULT_CARGO_LANE, $params = []): array
    {
        if (is_string($device)) {
            $device = Device::get($device, true);
        } elseif (is_int($device)) {
            $device = Device::get($device);
        }

        if ($device instanceof deviceModelObj) {
            $goods = Device::getGoodsByLane($device, $lane);
            if ($goods['lottery']) {
                $mcb_channel = $goods['lottery']['size'];
            } else {
                $mcb_channel = Device::cargoLane2Channel($device, $lane);
            }
            if ($mcb_channel == Device::CHANNEL_INVALID) {
                return error(State::ERROR, '不正确的货道！');
            }

            if (!$device->lockAcquire()) {
                return error(State::ERROR, '设备被占用，请重试！');
            }

            $data = array_merge(
                [
                    'online' => true,
                    'userid' => isset($user) ? $user->getName() : _W('username'),
                    'channel' => $mcb_channel,
                    'num' => 1,
                    'from' => 'web.admin',
                    'timeout' => settings('device.waitTimeout', DEFAULT_DEVICE_WAIT_TIMEOUT),
                ],
                $params
            );

            $log_data = [
                'goods' => Device::getGoodsByLane($device, $lane),
                'user' => isset($user) ? $user->profile() : _W('username'),
                'params' => $data,
                'payload' => $device->getPayload(),
            ];

            $res = $device->pull($data);

            $log_data['result'] = $res;

            $device->goodsLog(LOG_GOODS_TEST, $log_data);

            if (is_error($res)) {
                return $res;
            }

            //如果是营运人员测试，则不减少库存
            if (empty($params['keeper'])) {
                $device->resetPayload([$lane => -1]);
                $device->updateRemain();
            }

            $device->cleanError();
            $device->save();

            $result = ['message' => '出货成功！'];

            if ($device->isBlueToothDevice()) {
                $result['data'] = $res;
            }

            return $result;
        }

        return error(State::ERROR, '参数错误！');
    }

    /**
     * 用户通过指定公众号在指定设备上领取操作.
     * @param array $args
     * @return array
     * @throws Exception
     */
    public static function openDevice($args = []): array
    {
        ignore_user_abort(true);
        set_time_limit(0);

        //获取设备参数
        $devices = array_values(array_filter($args, function ($entry) {
            return $entry instanceof deviceModelObj;
        }));

        if (empty($devices)) {
            return error(State::ERROR, '设备为空');
        }

        /** @var deviceModelObj $device */
        $device = $devices[0];

        //获取用户参数
        $users = array_values(array_filter($args, function ($entry) {
            return $entry instanceof userModelObj;
        }));

        if (empty($users)) {
            return error(State::ERROR, '用户为空');
        }

        /** @var userModelObj $user */
        $user = $users[0];

        //获取订单参数
        $orders = array_values(array_filter($args, function ($entry) {
            return $entry instanceof orderModelObj;
        }));

        /** @var orderModelObj $order */
        $order = empty($orders) ? null : $orders[0];

        //获取公众号参数
        $accounts = array_values(array_filter($args, function ($entry) {
            return $entry instanceof accountModelObj;
        }));

        $account = empty($accounts) ? null : $accounts[0];

        //获取优惠券参数
        $vouchers = array_values(array_filter($args, function ($entry) {
            return $entry instanceof goods_voucher_logsModelObj;
        }));

        //获取商品参数
        /** @var goods_voucher_logsModelObj $voucher */
        $voucher = empty($vouchers) ? null : $vouchers[0];

        $level = intval($args['level']);
        $goods_id = intval($args['goodsId']);

        $params = [
            'device' => $device,
            'user' => $user,
            'account' => $account,
            'voucher' => $voucher,
        ];

        //事件：设备已锁定
        EventBus::on('device.beforeLock', $params);

        //锁定设备
        $retries = intval(settings('device.lockRetries', 0));
        $delay = intval(settings('device.lockRetryDelay', 1));

        if (!$device->lockAcquire($retries, $delay)) {
            return error(State::FAIL, '设备被占用，请重新扫描设备二维码');
        }

        //事件：设备已锁定
        EventBus::on('device.locked', $params);

        $goods = $device->getGoods($goods_id);
        if (empty($goods)) {
            return error(State::ERROR, '找不到对应的商品');
        }

        if ($goods['num'] < 1) {
            return error(State::FAIL, '对不起，已经被领完了');
        }

        if ($goods['lottery']) {
            $mcb_channel = intval($goods['lottery']['size']);
        } else {
            $mcb_channel = Device::cargoLane2Channel($device, $goods['cargo_lane']);
        }

        if ($mcb_channel == Device::CHANNEL_INVALID) {
            return error(State::FAIL, '商品货道配置不正确');
        }

        //让设备显示出货提示
        $device->showFakeQrcode(true);

        $log_data = [
            'user' => $user->profile(),
            'goods' => $goods,
            'payload' => $device->getPayload(),
            'account' => isset($account) ? [
                'name' => $account->name(),
                'title' => $account->title(),
            ] : [],
            'voucher' => isset($voucher) ? [
                'id' => $voucher->getId(),
            ] : [],
        ];

        if ($order) {
            $params['order'] = $order;
        }

        //开启事务
        $result = Util::transactionDo(function () use (&$params, $goods, $mcb_channel, &$log_data, $args) {
            /** @var deviceModelObj $device */
            $device = $params['device'];

            /** @var userModelObj $user */
            $user = $params['user'];

            /** @var accountModelObj $acc */
            $acc = $params['account'];

            /** @var orderModelObj $order */
            $order= $params['order'];

            /** @var goods_voucher_logsModelObj $voucher */
            $voucher = $params['voucher'];

            $order_data = [
                'openid' => $user->getOpenid(),
                'agent_id' => $device->getAgentId(),
                'device_id' => $device->getId(),
                'src' => Order::ACCOUNT,
                'name' => $goods['name'],
                'goods_id' => $goods['id'],
                'num' => 1,
                'price' => 0,
                'balance' => 0,
                'account' => $acc ? $acc->name() : '',
                'ip' => empty($args['ip']) ? CLIENT_IP : $args['ip'],
                'extra' => [
                    'goods' => $goods,
                    'device' => [
                        'imei' => $device->getImei(),
                        'name' => $device->getName(),
                    ],
                    'user' => $user->profile(),
                ],
            ];

            if ($args['orderId']) {
                $order_data['order_id'] = $args['orderId'];
            } else {
                $no_str = Util::random(32);
                $order_data['order_id'] = substr("U{$user->getId()}D{$device->getId()}{$no_str }", 0, 32);
            }

            if ($voucher) {
                $order_data['src'] = Order::VOUCHER;
                $order_data['extra']['voucher'] = [
                    'id' => $voucher->getId(),
                ];
            }

            $agent = $device->getAgent();
            if ($agent) {
                $order_data['extra']['agent'] = $agent->profile();
            }

            if ($order) {
                $order_data['extra'] = orderModelObj::serializeExtra($order_data['extra']);
                foreach ($order_data as $name => $val) {
                    $setter = 'set' . ucfirst($name);
                    $order->{$setter}($val);
                }
                if (!$order->save()) {
                    return error(State::FAIL, '领取失败，保存订单失败');
                }
            } else {
                $order = Order::create($order_data);
                if (empty($order)) {
                    return error(State::FAIL, '领取失败，创建订单失败');
                }

                $params['order'] = $order;

                try {
                    //事件：订单已经创建
                    EventBus::on('device.orderCreated', $params);
                } catch (Exception $e) {
                    return error($e->getCode() ?: State::ERROR, $e->getMessage());
                }
            }

            $user->remove('last');

            foreach ($params as $entry) {
                if ($entry && !$entry->save()) {
                    return error(State::FAIL, '无法保存数据，请重试');
                }
            }

            $data = [
                'online' => $args['online'] === false ? false : true,
                'channel' => $mcb_channel,
                'timeout' => settings('device.waitTimeout', DEFAULT_DEVICE_WAIT_TIMEOUT),
                'userid' => $user->getOpenid(),
                'num' => $order->getNum(),
                'from' => $acc ? $acc->name() : '',
                'user-agent' => $order->getExtraData('from.user_agent'),
                'ip' => $order->getExtraData('from.ip'),
            ];

            $loc = $device->settings('extra.location', []);
            if ($loc && $loc['lng'] && $loc['lat']) {
                $data['location']['device'] = [
                    'lng' => $loc['lng'],
                    'lat' => $loc['lat'],
                ];
            }

            $res = $device->pull($data);

            $log_data['params'] = $data;
            $log_data['result'] = $res;
            $log_data['order'] = $order->getId();
            $log_data['result'] = $res;

            if (is_error($res)) {
                $order->setResultCode($res['errno']);

                $device->setError($res['errno'], $res['message']);
                $device->scheduleErrorNotifyJob($res['errno'], $res['message']);

                try {
                    //事件：出货失败
                    EventBus::on('device.openFail', $params);
                } catch (Exception $e) {
                    //return error($e->getCode(), $e->getMessage());
                }
                if (Helper::NeedAutoRefund($device)) {
                    //退款任务
                    Job::refund($order->getOrderNO(), $res['message']);
                }

            } else {
                $order->setResultCode(0);

                if (isset($goods['cargo_lane'])) {
                    $device->resetPayload([$goods['cargo_lane'] => -1]);
                }

                if ($voucher) {
                    $voucher->setUsedUserId($user->getId());
                    $voucher->setUsedtime(time());
                    if (!$voucher->save()) {
                        return error(State::ERROR, '出货失败：使用取货码失败！');
                    }
                }
            }

            //出货失败后，只记录错误，不回退数据
            $order->setExtraData('pull.result', $res);

            if (!$order->save()) {
                return error(State::FAIL, '无法保存订单数据！');
            }

            $device->save();
            /**
             * 始终返回 true，是为了即使失败，仍然创建订单
             */
            return is_error($res) ? true : $res;
        });

        $device->goodsLog($level, $log_data);

        if (is_error($result)) {
            return $result;
        }

        $device->updateRemain();

        //事件：出货成功
        EventBus::on('device.openSuccess', $params);

        $order = $params['order'];

        return [
            'result' => $result,
            'orderid' => isset($order) ? $order->getId() : 0,
            'change' => isset($order) ? -$order->getBalance() : 0,
            'title' => '出货成功',
            'msg' => '请注意，出货成功',
        ];
    }

    /**
     * 在事务中执行指定函数.
     *
     * @param callable $cb 要执行的函数
     *
     * @return mixed
     */
    public static function transactionDo(callable $cb)
    {
        We7::pdo_begin();
        try {
            $ret = $cb();
            if (is_error($ret)) {
                We7::pdo_rollback();
            } else {
                We7::pdo_commit();
            }
            return $ret;
        } catch (Exception $e) {
            We7::pdo_rollback();
            return err($e->getMessage());
        }
    }

    /**
     * @return mixed
     */
    public static function getClientIp()
    {
        return We7::getip();
    }

    public static function convert2Baidu($lng, $lat): array
    {
        $ak = settings('device.location.baidu.ak', '8DlEgGEN0rDIVvnbaFLAn3rTxowBAjZm');
        $url = "https://api.map.baidu.com/geoconv/v1/?coords={$lng},{$lat}&from=3&to=5&ak={$ak}";

        $resp = ihttp::get($url);

        if (!is_error($resp)) {
            $res = json_decode($resp['content'], true);
            if ($res && $res['status'] == 0 && is_array($res['result'])) {
                $data = current($res['result']);

                return [
                    'lng' => $data['x'],
                    'lat' => $data['y'],
                ];
            }
        }

        return [];
    }

    public static function convert2Tencent($lng, $lat): array
    {
        $lbs_key = settings('user.location.appkey', DEFAULT_LBS_KEY);
        $url = 'https://apis.map.qq.com/ws/coord/v1/translate?';
        $params = urlencode("locations={$lat},{$lng}&type=5&&key={$lbs_key}");

        $resp = ihttp::get($url . $params);

        if (!is_error($resp)) {
            $res = json_decode($resp['content'], true);
            if ($res && $res['status'] == 0 && is_array($res['locations'])) {
                $data = current($res['locations']);

                return [
                    'lng' => $data['lng'],
                    'lat' => $data['lat'],
                ];
            }
        }

        return [];
    }

    public static function getLocation($lng, $lat): array
    {
        $lbs_key = settings('user.location.appkey', DEFAULT_LBS_KEY);
        $url = 'https://apis.map.qq.com/ws/geocoder/v1/?';
        $params = urlencode("location={$lat},{$lng}&key={$lbs_key}&get_poi=0");

        $resp = ihttp::get($url . $params);

        if (!is_error($resp)) {
            $res = json_decode($resp['content'], true);
            if ($res) {
                if ($res['status'] == 0 && is_array($res['result']['address_component'])) {
                    return [
                        'province' => $res['result']['address_component']['province'],
                        'city' => $res['result']['address_component']['city'],
                        'district' => $res['result']['address_component']['district'],
                        'address' => $res['result']['address'],
                    ];
                }
            }
        }

        return [];
    }

    public static function getDistance($from, $to)
    {
        $lbs_key = settings('user.location.appkey', DEFAULT_LBS_KEY);
        $url = "https://apis.map.qq.com/ws/distance/v1/matrix?mode=walking&from={$from['lat']},{$from['lng']}&to={$to['lat']},{$to['lng']}&key={$lbs_key}";
        $resp = ihttp::get($url);

        if (is_error($resp)) {
            return $resp;
        }

        $res = json_decode($resp['content'], true);
        if (empty($res)) {
            return err('请求失败，返回数据为空！');
        }

        if ($res['status'] != 0) {
            return err($res['message']);
        }

        if (is_array($res['result']['rows'])) {
            return intval(getArray($res, 'result.rows.0.elements.0.distance'));
        }

        return err('未知错误！');
    }

    /**
     * 根据两点间的经纬度计算距离，返回的单位是米(m)。
     * from: http://blog.mofeiwo.com/php实现两个坐标之间的距离/.
     *
     * @param $lng1
     * @param $lat1
     * @param $lng2
     * @param $lat2
     *
     * @return int
     */
    public static function calcDistance($lng1, $lat1, $lng2, $lat2)
    {
        //将角度转为狐度
        $radLat1 = deg2rad($lat1); //deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        return 2 * asin(
                sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))
            ) * 6378.137 * 1000;
    }

    /**
     * 是否需要对用户进行定位操作.
     * @param userModelObj $user
     * @param deviceModelObj $device
     * @return bool
     */
    public static function mustValidateLocation(userModelObj $user, deviceModelObj $device): bool
    {
        if (!$device->needValidateLocation()) {
            return false;
        }

        if (time() - $user->settings('last.location.time') < settings('user.scanAlive', VISIT_DATA_TIMEOUT)) {
            if ($user->settings('last.location.validated')) {
                return false;
            }
        }

        return true;
    }

    /**
     * 返回用户还需要关注的公众号列表.
     *
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param accountModelObj $account
     * @param array $excepts
     *
     * @return array
     */
    public static function getRequireAccounts(deviceModelObj $device, userModelObj $user, accountModelObj $account, array $excepts = []): array
    {
        $accounts = [];

        //获取多个关注公众号设置
        $qrcodes = $account->get('qrcodesData', []);
        if ($qrcodes && is_array($qrcodes)) {
            $accounts = $qrcodes;
        }

        //如果没有开启关注多个公众号，但开启了公众号推广，则加入推广公众号
        if (empty($accounts) && settings('misc.accountsPromote')) {
            $res = Account::match($device, $user, ['unfollow']);
            if ($res && !isset($res[$account->getUid()])) {
                $acc = current($res);

                $accounts[$acc['uid']] = [
                    'img' => $acc['qrcode'],
                    'xid' => $acc['uid'],
                    'url' => $acc['url'],
                    'descr' => $acc['descr'],
                ];
            }
        }

        //去掉主号
        unset($accounts[$account->getUid()]);

        //去掉已经关注过的号
        $visited_accounts = $user->settings('last.accounts', []);
        $visited_accounts = is_array($visited_accounts) ? $visited_accounts : [];

        //排除
        if ($excepts) {
            $excepts = is_array($excepts) ? $excepts : [$excepts];
            foreach ($excepts as $uid) {
                if ($uid) {
                    $visited_accounts[$uid] = time();
                }
            }

            $user->updateSettings('last.accounts', $visited_accounts);
        }

        $accounts = array_diff_key($accounts, $visited_accounts);

        return array_values($accounts);
    }

    /**
     * 获取页面模板变量.
     *
     * @param mixed $objs
     *
     * @return array
     */
    public static function getTplData(array $objs = []): array
    {
        $data = [
            'module' => APP_NAME,
            'site' => [
                'title' => settings('misc.siteTitle', DEFAULT_SITE_TITLE),
                'copyrights' => settings('misc.siteCopyrights', DEFAULT_COPYRIGHTS),
                'warning' => settings('misc.siteWarning', ''),
            ],
            'page' => [
                'title' => DEFAULT_SITE_TITLE,
            ],
            'theme' => settings('device.get.theme', 'default'),
            'exclude' => [],
        ];

        foreach ($objs as $index => $entry) {
            if (is_string($index)) {
                setArray($data, $index, $entry);
                continue;
            }

            if ($entry instanceof userModelObj) {
                $data['user'] = [
                    'id' => $entry->getId(),
                    'nickname' => $entry->getNickname(),
                    'avatar' => $entry->getAvatar(),
                    '_obj' => $entry,
                ];

                if (App::isUserCenterEnabled()) {
                    $data['user']['balance'] = $entry->getBalance()->total();
                    $data['balance'] = [
                        'title' => settings('user.balance.title', DEFAULT_BALANCE_TITLE),
                        'unit' => settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME),
                        'price' => settings('user.balance.price', 0),
                    ];
                }
            } elseif ($entry instanceof deviceModelObj) {
                $data['device'] = [
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                    'imei' => $entry->getImei(),
                    'shadowId' => $entry->getShadowId(),
                    '_obj' => $entry,
                ];

                $agent = $entry->getAgent();
                //获取代理商页面设置
                if ($agent) {
                    $agent_data = $agent->getAgentData();
                    if ($agent_data) {
                        if ($agent_data['misc']['siteTitle']) {
                            $data['site']['title'] = $agent_data['misc']['siteTitle'];
                        }
                        if ($agent_data['misc']['copyrights']) {
                            $data['site']['copyrights'] = $agent_data['misc']['copyrights'];
                        }
                    }
                }
            } elseif ($entry instanceof accountModelObj) {
                $data['account'] = [
                    'title' => $entry->getTitle(),
                    'descr' => $entry->getDescription(),
                    'img' => $entry->getImg(),
                    'qrcode' => $entry->getQrcode(),
                    'clr' => $entry->getClr(),
                    '_obj' => $entry,
                ];
                if (App::isUserCenterEnabled()) {
                    $data['account']['balanceDeductNum'] = $entry->getBalanceDeductNum();
                }
            } elseif (is_array($entry)) {
                foreach ($entry as $key => $val) {
                    setArray($data, $key, $val);
                }
            }
        }

        return $data;
    }

    /**
     * @param $length
     * @param bool $numeric
     *
     * @return mixed
     */
    public static function random($length, $numeric = false)
    {
        return We7::random($length, $numeric);
    }

    /**
     * 随机颜色值
     *
     * @return string
     */
    public static function randColor(): string
    {
        $arr = array();
        for ($i = 0; $i < 6; ++$i) {
            $arr[] = dechex(rand(0, 15));
        }

        return '#' . implode('', $arr);
    }

    /**
     * @param string $do
     * @param array $params
     * @return mixed
     */
    public static function url($do = '', array $params = [], $eid = true)
    {
        $params['m'] = APP_NAME;

        if ($eid && request('eid')) {
            $params['eid'] = request('eid');
        }

        return We7::url("site/entry/{$do}", $params);
    }

    /**
     * @param string $do
     * @param array $params
     * @param bool $full_url
     * @return string
     */
    public static function murl($do = '', array $params = [], $full_url = true): string
    {
        $params['do'] = $do;
        $params['m'] = APP_NAME;
        $url = We7::murl('entry', $params);

        $str = [];
        $replacements = [];

        if (App::isHttpsWebsite()) {
            $str[] = 'http://';
            $replacements[] = 'https://';
        }

        $str[] = 'addons/' . APP_NAME . '/';
        $str[] = 'payment/';

        if ($full_url) {
            $url = _W('siteroot') . 'app/' . $url;
            $str[] = './';
        }

        return str_replace($str, $replacements, $url);
    }

    public static function shortMobileUrl(string $do, array $params = []): string
    {
        $url = Util::murl($do, $params);
        return self::shortUrl($url);
    }

    public static function shortUrl(string $url): string
    {
        return $url;
        //微信短网址服务已于2021年3月15日下线，该功能暂停
        //$res = Wx::shortUrl($url);
        //return is_error($res) || empty($res['short_url']) ? $url : $res['short_url'];
    }

    /**
     * 激活设备.
     *
     * @param $imei
     * @param array $params
     *
     * @return mixed
     */
    public static function activeDevice($imei, array $params = [])
    {
        $res = CtrlServ::query("device/{$imei}/active", [], '', '', 'PUT');
        if (is_error($res)) {
            return $res;
        }

        //刷新域名转发缓存
        $url = str_replace('{imei}', urlencode($imei), settings('ctrl.qrcode.url', FLUSH_DEVICE_FORWARDER_URL));
        if (file_get_contents($url) === false) {
            return error(State::ERROR, '刷新域名缓存失败！');
        }

        $device = Device::get($imei, true);
        if (empty($device)) {
            $data = array_merge(
                [
                    'name' => $imei,
                    'imei' => $imei,
                ],
                $params
            );

            //设置默认型号
            $typeid = settings('device.multi-types.first', 0);
            $device_type = DeviceTypes::get($typeid);
            if (!empty($device_type)) {
                $data['device_type'] = $typeid;
            }

            $device = Device::create($data);
            if (empty($device)) {
                return error(State::ERROR, '创建设备失败！');
            }

            $device->updateQrcode(true);
            $device->updateRemain();

            //更新公众号缓存
            $device->updateAccountData();
            $device->updateScreenAdvsData();

            $device->updateAppId();

            $device->save();
        }

        return $device;
    }

    /**
     * 导出excel.
     *
     * @param string $filename
     * @param array $header
     * @param array $data
     */
    public static function exportExcel($filename = '', array $header = [], array $data = [])
    {
        header('Content-type:application/vnd.ms-excel');
        header('Content-Disposition:filename=' . $filename . '.xls');

        $tab_header = implode("\t", $header);
        $str_export = $tab_header . "\r\n";
        foreach ($data as $row) {
            $str_export .= implode("\t", $row) . "\r\n";
        }

        exit($str_export);
    }

    public static function getWe7Material($typename, $page, $page_size = DEFAULT_PAGESIZE): array
    {
        $title = '';
        $list = [];

        if ($typename == 'text') {
            $title = '填写推送消息的文本';
        } elseif ($typename == 'image') {
            $title = '选择推送消息的图片';
            $page = max(1, intval($page));
            $page_size = max(1, $page_size);
            We7::load()->model('material');
            $list = We7::material_list('image', MATERIAL_WEXIN, ['page_index' => $page, 'page_size' => $page_size]);
        } elseif ($typename == 'mpnews') {
            $title = '选择推送消息的图文';
            We7::load()->model('material');
            $list = We7::material_news_list(MATERIAL_WEXIN)['material_list'];
        }

        return ['title' => $title, 'list' => $list];
    }

    public static function getAgentFNs($enable = true): array
    {
        $val = $enable ? 1 : 0;
        return [
            'F_tj' => $val, //统计管理
            'F_xj' => $val, //下级管理
            'F_sb' => $val, //设备管理
            'F_zc' => $val, //设备注册
            'F_qz' => $val, //缺货设备
            'F_gz' => $val, //故障设备
            'F_yy' => $val, //运营人员
            'F_gg' => $val, //广告管理
            'F_xf' => $val, //吸粉管理
            'F_pt' => $val, //平台管理
            'F_wt' => $val, //常见问题
            'F_wd' => $val, //文档中心
            'F_xh' => $val, //型号管理
            'F_sp' => $val, //商品管理
        ];
    }

    public static function parseAgentFNsFromGPC(): array
    {
        $FNs = self::getAgentFNs(false);
        foreach ($FNs as $index => &$enable) {
            $enable = empty(request($index)) ? 0 : 1;
        }
        return $FNs;
    }

    public static function parseIdsFromGPC()
    {
        $ids = [];

        $raw = request('ids');
        if ($raw) {
            if (is_string($raw)) {
                $ids = explode(',', $raw);
            } elseif (is_array($raw)) {
                $ids = $raw;
            } else {
                $ids = [intval($raw)];
            }
            foreach ($ids as $index => $id) {
                $id = intval($id);
                if ($id > 0) {
                    $ids[$index] = $id;
                }
            }
        }

        return $ids;
    }

    private static function parseResult($line): array
    {
        $result = [];
        $data = explode(',', $line);

        foreach ($data as $entry) {
            $entry = trim($entry);
            if (empty($entry)) {
                continue;
            }

            if (!isset($result['agent']) && preg_match(REGULAR_MOBILE, $entry)) {
                $agent = Agent::query(['mobile' => $entry])->findOne();
                if ($agent) {
                    $result['agent'] = $agent;
                    continue;
                }
            }

            if (!isset($result['keeper']) && preg_match(REGULAR_MOBILE, $entry)) {
                $keeper = Keeper::query(['mobile' => $entry])->findOne();
                if ($keeper) {
                    $result['keeper'] = $keeper;
                    continue;
                }
            }

            if (!isset($result['device_type']) && is_numeric($entry)) {
                $device_type = DeviceTypes::get($entry);
                if ($device_type) {
                    $result['device_type'] = $device_type;
                    continue;
                }
            }

            $matches = [];
            if ($res = preg_match_all('/^[abc]{1,3}$/i', $entry, $matches)) {
                if (!isset($result['screen']) && stripos($matches[0][0], 'a')) {
                    $result['screen'] = true;
                }
                if (!isset($result['disinfectant']) && stripos($matches[0][0], 'b')) {
                    $result['disinfectant'] = true;
                }
                if (!isset($result['battery']) && stripos($matches[0][0], 'c')) {
                    $result['battery'] = true;
                }
                continue;
            }

            if (!isset($result['imei']) && is_numeric($entry) && strlen($entry) >= 15) {
                $result['imei'] = $entry;
                continue;
            }

            if (!isset($result['uid'])) {
                $result['uid'] = strval($entry);
            }
        }

        return $result;
    }

    function csvToArray($filename): array
    {
        $result = [];
        if (($handle = fopen($filename, "r")) !== FALSE) {
            while (($line = fgets($handle)) !== FALSE) {
                $result[] = self::parseResult($line);
            }
            fclose($handle);
        }
        return $result;
    }

    public static function descAssignedStatus($assign_data): string
    {
        if (isEmptyArray($assign_data) || (isset($assign_data['all']) && empty($assign_data['all']))) {
            return '没有分配任何设备';
        } elseif ($assign_data['all']) {
            return '已分配全部设备';
        }
        return '已指定部分设备';
    }

    public static function buildUrl($parsed_url): string
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    public static function getDeviceAdvs($device_uid, $type, $max_total): array
    {
        $device = Device::get($device_uid, true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        $result = [];
        foreach ($device->getAdvs($type) as $item) {
            $data = [
                'id' => $item['id'],
                'title' => $item['title'],
                'data' => $item['extra'],
            ];
            if ($data['data']['image']) {
                $data['data']['image'] = Util::toMedia($data['data']['image']);
            } elseif ($data['data']['images']) {
                foreach ($data['data']['images'] as &$image) {
                    $image = Util::toMedia($image);
                }
            }
            $result[] = $data;
            if ($max_total > 0 && count($result) > $max_total) {
                break;
            }
        }

        return $result;
    }

    /**
     * 获取后台设置的管理员维护公众号
     */
    public static function getAdminAccount(): array
    {
        $admin_account_name = settings('misc.adminAccount');
        if ($admin_account_name) {
            $uid = Account::makeUID($admin_account_name);
            $account = Account::findOne(['uid' => $uid]);
            if ($account) {
                return [
                    'uid' => $account->getUid(),
                    'title' => $account->getTitle(),
                    'descr' => $account->getDescription(),
                    'img' => strval(Util::toMedia($account->getImg())),
                    'qrcode' => strval(Util::toMedia($account->getQrcode())),
                    'clr' => $account->getClr(),
                    'url' => $account->getUrl(),
                ];
            }
        }
        return [];
    }

    public static function getImageProxyURL($image_url): string
    {
        if (empty($image_url)) {
            return '';
        }

        $url = App::imageProxyURL();
        if (empty($url)) {
            return $image_url;
        }

        $signStr = '';

        $secret = App::imageProxySecretKey();
        if ($secret) {
            $signStr = ',s' . strtr(base64_encode(hash_hmac('sha256', $image_url, $secret, true)), '+/', '-_');
            $url = rtrim($url, '\\/');
        }

        return "{$url}{$signStr}/{$image_url}";
    }

    public static function isAliAppContainer(): bool
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if (empty($user_agent)) {
            return false;
        }
        $alipay_arr = ['aliapp', 'alipayclient', 'alipay'];
        foreach ($alipay_arr as $val) {
            if (strpos($user_agent, $val) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function get(string $url, int $timeout = 3): ?string
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        if (stripos($url, "https://") !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            return null;
        }

        return $response;
    }

    public static function post(string $url, array $data = [], bool $json = true, int $timeout = 3): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        if (stripos($url, "https://") !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($ch, CURLOPT_POST, true);
        if ($json) {
            $json_str = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_str);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_str)
            ]);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);

        curl_close($ch);

        if (empty($response)) {
            return error(State::ERROR, '请求失败或者返回空数据！');
        }

        $result = json_decode($response, JSON_OBJECT_AS_ARRAY);

        return isset($result) ? $result : error(State::ERROR, '无法解析返回的数据！');
    }

    public static function redirect(string $url)
    {
        header("Location:{$url}", true, 302);
    }

    public static function getProvinceList(): array
    {
        return [
            'p1' => '北京',
            'p2' => '天津',
            'p3' => '上海',
            'p4' => '重庆',
            'p5' => '河北',
            'p6' => '山西',
            'p7' => '辽宁',
            'p8' => '吉林',
            'p9' => '黑龙江',
            'p10' => '浙江',
            'p11' => '江苏',
            'p12' => '安徽',
            'p13' => '福建',
            'p14' => '江西',
            'p15' => '山东',
            'p16' => '河南',
            'p17' => '湖北',
            'p18' => '湖南',
            'p19' => '广东',
            'p20' => '海南',
            'p21' => '四川',
            'p22' => '贵州',
            'p23' => '云南',
            'p24' => '陕西',
            'p25' => '甘肃',
            'p26' => '青海',
            'p27' => '内蒙古',
            'p28' => '广西',
            'p29' => '西藏',
            'p30' => '宁夏',
            'p31' => '新疆',
        ];
    }
}
