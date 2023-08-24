<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

//框架提供
use zovye\model\accountModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\userModelObj;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\agentModelObj;

class Util
{
    public static function config($sub = '')
    {
        static $config = null;
        if (!isset($config)) {
            $config_filename = ZOVYE_CORE_ROOT.'config.php';
            if (file_exists($config_filename)) {
                $config = require_once($config_filename);
            } else {
                $config = _W('config', []);
            }
        }

        return getArray($config, $sub);
    }

    /**
     * 获取页面参数
     * @param $name string 如果指定参数是数组，则取回数组中指定键值
     * @param $throw_error bool
     * @return mixed
     */
    public static function getTemplateVar(string $name = '', bool $throw_error = false)
    {
        $var = $GLOBALS['_tpl_var_'][0];
        if ($name) {
            $var = getArray($var, $name);
        }

        if ($throw_error && is_null($var)) {
            throw new InvalidArgumentException('缺少必须的模块参数！');
        }

        return $var;
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

        return $traits && in_array(__NAMESPACE__.'\\traits\\'.$traitName, $traits);
    }

    public static function getTokenValue(): string
    {
        return self::random(16);
    }

    public static function setErrorHandler()
    {
        if (DEBUG) {
            error_reporting(E_ALL ^ E_NOTICE);
        } else {
            error_reporting(0);
        }

        set_error_handler(function ($severity, $str, $file, $line, $context) {
            Log::error('app', [
                'level' => $severity,
                'str' => $str,
                'file' => $file,
                'line' => $line,
                //'context' => $context,
            ]);
        }, E_ALL ^ E_NOTICE);

        set_exception_handler(function (Throwable $e) {
            Log::error('app', [
                'type' => get_class($e),
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                //'trace' => $e->getTraceAsString(),
            ]);
        });
    }

    public static function createApiRedirectFile(string $filename, string $do, array $params = [], callable $fn = null)
    {
        We7::make_dirs(dirname(ZOVYE_ROOT.$filename));

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
            $header_str .= "\$_SERVER['$name'] = '$val';\r\n";
        }

        $memo = !empty($params['memo']) ? strval($params['memo']) : 'API转发程序';
        unset($params['memo']);

        if ($do) {
            $params['do'] = $do;
        }

        $appName = APP_NAME;
        $uniacid = We7::uniacid();
        $appPath = realpath(ZOVYE_ROOT.'../../app');

        $content = "<?php
/**
 * $memo
 *
 * @author jin@stariture.com
 * @url www.stariture.com
 */

$header_str
\$_GET['m'] = '$appName';
\$_GET['i'] = $uniacid;
\$_GET['c'] = 'entry';
";
        foreach ($params as $name => $val) {
            $content .= "\$_GET['$name'] = '$val';\r\n";
        }

        if ($fn) {
            $content .= $fn();
        }

        $content .= "
chdir('$appPath');
include './index.php';
";

        return file_put_contents(ZOVYE_ROOT.$filename, $content);
    }

    /**
     * 检查订单数量是否达到指定数量，true 表示已达到，false表示没有
     * @param accountModelObj $account
     * @param userModelObj|null $user
     * @param array $params
     * @param int $limit
     * @return bool
     */
    public static function checkLimit(
        accountModelObj $account,
        userModelObj $user = null,
        array $params = [],
        int $limit = 0
    ): bool {
        $result = CacheUtil::cachedCall(0, function () use ($account, $user, $params, $limit) {
            $arr = [];
            if ($account->isTask()) {
                $cond = array_merge($params, [
                    'account_id' => $account->getId(),
                ]);
                if ($user) {
                    $cond['user_id'] = $user->getId();
                }
                $arr[] = [
                    BalanceLog::query($cond),
                    'count',
                ];
            } elseif ($account->isQuestionnaire()) {
                $cond = array_merge($params, [
                    'level' => $account->getId(),
                ]);
                if ($user) {
                    $cond['title'] = $user->getOpenid();
                }
                $arr[] = [
                    Questionnaire::log($cond),
                    'count',
                ];
            } else {
                $cond = array_merge($params, [
                    'account_id' => $account->getId(),
                ]);
                if ($user) {
                    $cond['user_id'] = $user->getId();
                }
                $arr[] = [
                    BalanceLog::query($cond),
                    'count',
                ];
                $cond2 = array_merge($params, [
                    'account' => $account->getName(),
                ]);
                if ($user) {
                    $cond2['openid'] = $user->getOpenid();
                }
                $arr[] = [
                    Order::query($cond2),
                    'sum',
                ];
            }

            foreach ($arr as $e) {
                list($query, $m) = $e;

                $query->limit($limit);
                $total = $m == 'sum' ? $query->sum('num') : $query->count();

                if ($total >= $limit) {
                    return true;
                }

                $limit -= $total;
            }

            // 条件不满足时，抛出异常，抑制缓存
            throw new RuntimeException();
        }, 'checkLimit', $account->getId(), $user ? $user->getId() : 0, $params, $limit);

        return !is_error($result);
    }

    /**
     * 检查用户是否符合公众号设置的限制条件
     * @param userModelObj $user
     * @param accountModelObj $account
     * @param array $params
     * @return array|bool|mixed
     */
    public static function checkAccountLimits(userModelObj $user, accountModelObj $account, array $params = [])
    {
        //检查性别，手机限制
        $limits = $account->get('limits');
        if (is_array($limits)) {
            $limit_fn = [
                'male' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 1) {
                        return err('不允许男性用户');
                    }

                    return true;
                },
                'female' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 2) {
                        return err('不允许女性用户');
                    }

                    return true;
                },
                'unknown_sex' => function ($val) use ($user) {
                    if ($val == 0 && $user->settings('fansData.sex') == 0) {
                        return err('不允许未知性别用户');
                    }

                    return true;
                },
                'ios' => function ($val) {
                    if ($val == 0 && Session::getUserPhoneOS() == 'ios') {
                        return err('不允许ios手机');
                    }

                    return true;
                },
                'android' => function ($val) {
                    if ($val == 0) {
                        $os = Session::getUserPhoneOS();
                        if ($os == 'android' || $os == 'unknown') {
                            return err('不允许android手机');
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

            if (!isEmptyArray($limits['area'])) {
                $info = LocationUtil::getIpInfo(CLIENT_IP);
                if ($info) {
                    if ($limits['area']['province'] && $info['data']['province'] != $limits['area']['province']) {
                        return err('区域（省）不允许！');
                    }

                    if ($limits['area']['city'] && $info['data']['city'] != $limits['area']['city']) {
                        return err('区域（市）不允许！');
                    }
                }
            }
        }

        $is_new_user = empty(Order::getFirstOrderOfUser($user));

        if ($params['unfollow'] || in_array('unfollow', $params, true)) {
            if (!$is_new_user && self::checkLimit($account, $user, [], 1)) {
                return err('您已经完成了该任务！');
            }
        }

        $sc_name = $account->getScname();

        if ($sc_name == Schema::DAY) {
            $time = new DateTimeImmutable('00:00');
        } elseif ($sc_name == Schema::WEEK) {
            $time = date('D') == 'Mon' ? new DateTimeImmutable('00:00') : new DateTimeImmutable('last Mon 00:00');
        } elseif ($sc_name == Schema::MONTH) {
            $time = new DateTimeImmutable('first day of this month 00:00');
        } else {
            return err('任务设置不正确！');
        }

        //count，单个用户在每个周期内可领取数量
        $count = $account->getCount();
        if ($count > 0) {
            $desc = [
                Schema::DAY => '今天已经领过了，明天再来吧！',
                Schema::WEEK => '下个星期再来试试吧！',
                Schema::MONTH => '这个月的免费额度已经用完啦！',
            ];

            if (!$is_new_user && self::checkLimit(
                    $account,
                    $user,
                    ['createtime >=' => $time->getTimestamp(),],
                    $count
                )) {
                return err($desc[$sc_name]);
            }
        }

        //scCount, 所有用户在每个周期内总数量
        $sc_count = $account->getSccount();
        if ($sc_count > 0) {
            if (!$is_new_user && self::checkLimit($account, null, [
                    'createtime >=' => $time->getTimestamp(),
                ], $sc_count)) {
                return err('任务免费额度已用完！');
            }
        }

        //total，单个用户累计可领取数量
        $total = $account->getTotal();
        if ($total > 0) {
            if (!$is_new_user && self::checkLimit($account, $user, [], $total)) {
                return err('您已经完成这个任务了！');
            }
        }

        //$orderLimits，最大订单数量
        $order_limits = $account->getOrderLimits();
        if ($order_limits > 0) {
            if (!$is_new_user && self::checkLimit($account, null, [], $order_limits)) {
                return err('公众号免费额度已用完！！');
            }
        }

        return true;
    }

    public static function checkBalanceAvailable(userModelObj $user, accountModelObj $account)
    {
        if ($account->getBonusType() != Account::BALANCE) {
            return err('公众号没有配置积分奖励！');
        }

        return self::checkAccountLimits($user, $account);
    }

    /**
     * 检查用户是否被限制，则返回true，否则返回false
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return bool
     */
    public static function checkFlashEggDeviceLimit(deviceModelObj $device, userModelObj $user): bool
    {
        $limit = $device->settings('extra.limit', []);
        if (isEmptyArray($limit)) {
            return false;
        }

        if (in_array($limit['scname'], [Schema::DAY, Schema::WEEK, Schema::MONTH])) {
            if ($limit['scname'] == Schema::DAY) {
                $time = new DateTimeImmutable('00:00');
            } elseif ($limit['scname'] == Schema::WEEK) {
                $time = date('D') == 'Mon' ? new DateTimeImmutable('00:00') : new DateTimeImmutable('last Mon 00:00');
            } elseif ($limit['scname'] == Schema::MONTH) {
                $time = new DateTimeImmutable('first day of this month 00:00');
            } else {
                $time = null;
            }

            if ($time) {
                //单个用户周期内限制
                if ($limit['count'] > 0) {
                    $query = Order::query([
                        'src' => [Order::FREE, Order::ACCOUNT],
                        'device_id' => $device->getId(),
                        'openid' => $user->getOpenid(),
                        'createtime >=' => $time->getTimestamp(),
                    ]);
                    $query->limit($limit['count']);
                    if ($query->sum('num') >= $limit['count']) {
                        return true;
                    }
                }
                //所有用户周期内限制
                if ($limit['sccount'] > 0) {
                    $query = Order::query([
                        'src' => [Order::FREE, Order::ACCOUNT],
                        'device_id' => $device->getId(),
                        'createtime >=' => $time->getTimestamp(),
                    ]);
                    $query->limit($limit['sccount']);
                    if ($query->sum('num') >= $limit['sccount']) {
                        return true;
                    }
                }
            }
        }

        //单个用户累计限制
        if ($limit['total'] > 0) {
            $query = Order::query([
                'src' => [Order::FREE, Order::ACCOUNT],
                'device_id' => $device->getId(),
                'openid' => $user->getOpenid(),
            ]);
            $query->limit($limit['total']);
            if ($query->sum('num') >= $limit['total']) {
                return true;
            }
        }

        //所有用户累计限制
        if ($limit['all'] > 0) {
            $query = Order::query([
                'src' => [Order::FREE, Order::ACCOUNT],
                'device_id' => $device->getId(),
            ]);
            $query->limit($limit['all']);
            if ($query->sum('num') >= $limit['all']) {
                return true;
            }
        }

        return false;
    }


    /**
     * 判断用户在指定公众号以及指定设备是否还有免费额度.
     *
     * @param userModelObj $user 用户
     * @param accountModelObj $account 公众号
     * @param deviceModelObj $device 设备
     * @param array $params 更多条件
     *
     * @return bool|array
     */
    public static function checkAvailable(
        userModelObj $user,
        accountModelObj $account,
        deviceModelObj $device,
        array $params = []
    ) {
        $res = self::checkFreeOrderLimits($user, $device);
        if (is_error($res)) {
            return $res;
        }

        if (empty($params['ignore_assigned'])) {
            $assign_data = $account->settings('assigned', []);
            if (!DeviceUtil::isAssigned($device, $assign_data)) {
                return err('没有允许从这个设备访问该公众号！');
            }
        }

        if (App::isFlashEggEnabled()) {
            if (self::checkFlashEggDeviceLimit($device, $user)) {
                return err('领取数量趣过设备限制！');
            }
            $totalPerDevice = $account->getTotalPerDevice();
            if ($totalPerDevice > 0 && self::checkLimit(
                    $account,
                    $user,
                    ['device_id' => $device->getId()],
                    $totalPerDevice
                )) {
                return err('领取数量已经达到单台设备最大领取限制！');
            }
        }

        return self::checkAccountLimits($user, $account, $params);
    }

    public static function checkFreeOrderLimits(userModelObj $user, deviceModelObj $device)
    {
        //每日免费额度限制
        if (Util::getUserTodayFreeNum($user, $device) < 1) {
            return err('今天领的太多了，明天再来吧！');
        }

        //全部免费额度限制
        if (Util::getUserFreeNum($user, $device) < 1) {
            return err('您的免费额度已用完！');
        }

        return true;
    }

    public static function getFreeOrderLimits(userModelObj $user, deviceModelObj $device)
    {
        //每日免费额度限制
        $today = Util::getUserTodayFreeNum($user, $device);
        if ($today < 1) {
            return 0;
        }

        //全部免费额度限制
        $all = Util::getUserFreeNum($user, $device);
        if ($all < 1) {
            return 0;
        }

        return min($today, $all);
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
                $max_free = $agent->getAgentData('misc.maxFree', 0);
            }

            $max_free = $max_free > 0 ? $max_free : (int)settings('user.maxFree', 0);

            if ($max_free > 0) {
                $remain = max(0, $max_free - $user->getTodayFreeTotal());
            } else {
                $remain = App::getOrderMaxGoodsNum();
            }
        }

        return $remain;
    }

    public static function getUserFreeNum(userModelObj $user, deviceModelObj $device): int
    {
        $remain = null;

        if (is_null($remain)) {
            $max_free = 0;

            $agent = $device->getAgent();
            if ($agent) {
                $max_free = $agent->getAgentData('misc.maxTotalFree', 0);
            }

            $max_free = $max_free > 0 ? $max_free : (int)settings('user.maxTotalFree', 0);

            if ($max_free > 0) {
                $remain = max(0, $max_free - $user->getFreeTotal());
            } else {
                $remain = App::getOrderMaxGoodsNum();
            }
        }

        return $remain;
    }

    public static function updateOrderCounters(orderModelObj $order)
    {
        if (!Locker::try("order:counter:{$order->getId()}")) {
            return false;
        }

        $uid = App::uid(6);
        $counters = [
            "$uid:order:all" => function () {
                return Order::query()->count();
            },
        ];

        $createtime = $order->getCreatetime();
        $counters[$uid.':order:month:'.date('Y-m', $createtime)] = function () use ($createtime) {
            $start = new DateTime("@$createtime");
            $start->modify('first day of this month 00:00');
            $end = new DateTime("@$createtime");
            $end->modify('first day of next month 00:00');

            return Order::query([
                'createtime >=' => $start->getTimestamp(),
                'createtime <' => $end->getTimestamp(),
            ])->count();
        };
        $counters[$uid.':order:day:'.date('Y-m-d', $createtime)] = function () use ($createtime) {
            $start = new DateTime("@$createtime");
            $start->modify('00:00');
            $end = new DateTime("@$createtime");
            $end->modify('next day 00:00');

            return Order::query([
                'createtime >=' => $start->getTimestamp(),
                'createtime <' => $end->getTimestamp(),
            ])->count();
        };

        $device = $order->getDevice();
        if ($device) {
            $counters["device:{$device->getId()}:order:all"] = function () use ($device) {
                return Order::query([
                    'device_id' => $device->getId(),
                ])->count();
            };
            $counters["device:{$device->getId()}:order:month:".date('Y-m', $createtime)] = function () use (
                $device,
                $createtime
            ) {
                $start = new DateTime("@$createtime");
                $start->modify('first day of this month 00:00');
                $end = new DateTime("@$createtime");
                $end->modify('first day of next month 00:00');

                return Order::query([
                    'device_id' => $device->getId(),
                    'createtime >=' => $start->getTimestamp(),
                    'createtime <' => $end->getTimestamp(),
                ])->count();
            };
            $counters["device:{$device->getId()}:order:day:".date('Y-m-d', $createtime)] = function () use (
                $device,
                $createtime
            ) {
                $start = new DateTime("@$createtime");
                $start->modify('00:00');
                $end = new DateTime("@$createtime");
                $end->modify('next day 00:00');

                return Order::query([
                    'device_id' => $device->getId(),
                    'createtime >=' => $start->getTimestamp(),
                    'createtime <' => $end->getTimestamp(),
                ])->count();
            };
        }

        $agent = $order->getAgent();
        if ($agent) {
            $counters["agent:{$agent->getId()}:order:all"] = function () use ($agent) {
                return Order::query([
                    'agent_id' => $agent->getId(),
                ])->count();
            };
            $counters["agent:{$agent->getId()}:order:month:".date('Y-m', $createtime)] = function () use (
                $agent,
                $createtime
            ) {
                $start = new DateTime("@$createtime");
                $start->modify('first day of this month 00:00');
                $end = new DateTime("@$createtime");
                $end->modify('first day of next month 00:00');

                return Order::query([
                    'agent_id' => $agent->getId(),
                    'createtime >=' => $start->getTimestamp(),
                    'createtime <' => $end->getTimestamp(),
                ])->count();
            };
            $counters["agent:{$agent->getId()}:order:day:".date('Y-m-d', $createtime)] = function () use (
                $agent,
                $createtime
            ) {
                $start = new DateTime("@$createtime");
                $start->modify('00:00');
                $end = new DateTime("@$createtime");
                $end->modify('next day 00:00');

                return Order::query([
                    'agent_id' => $agent->getId(),
                    'createtime >=' => $start->getTimestamp(),
                    'createtime <' => $end->getTimestamp(),
                ])->count();
            };
        }

        return DBUtil::transactionDo(function () use ($order, $counters) {
            foreach ($counters as $uid => $initFN) {
                if (!Counter::increment($uid, 1, $initFN)) {
                    return err('fail');
                }
            }

            return true;
        });
    }

    /**
     * 订单统计
     *
     * @param orderModelObj $order 订单对象
     *
     */
    public static function orderStatistics(orderModelObj $order)
    {
        $name = $order->getAccount();
        if ($name) {
            $account = Account::findOneFromName($name);
            if ($account) {
                $order_limits = $account->getOrderLimits();
                if ($order_limits > 0) {
                    //更新公众号统计，并检查吸粉总量
                    $total = Order::query(['account' => $account->getName()])->limit($order_limits + 1)->count();
                    if ($total >= $order_limits) {
                        $account->setState(Account::BANNED);
                        Account::updateAccountData();
                    }
                }
            }
        }
    }


    /**
     * 获取需要通知的openid list.
     *
     * @param agentModelObj $agent
     * @param string $event
     * @return array
     */
    public static function getNotifyOpenIds(agentModelObj $agent, string $event): array
    {
        $result = [];

        if ($event) {
            if ($agent->getAgentData("notice.$event") && !$agent->isBanned()) {
                $result[$agent->getId()] = $agent->getOpenid();
            }

            foreach ((array)$agent->getAgentData('partners') as $user_id => $data) {
                $user = User::get($user_id);
                if ($user && !$user->isBanned()) {
                    $enabled = $user->settings("partnerData.notice.$event");
                    if (!isset($enabled) || $enabled) {
                        $result[$user->getId()] = $user->getOpenid();
                    }
                }
            }
        }

        return array_values($result);
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
     * 格式化时间.
     *
     * @param $ts
     *
     * @return string
     *
     * @throws
     */
    public static function getFormattedPeriod($ts): string
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
     * 修正微擎payResult回调时，toMedia函数工作不正常的问题.
     *
     * @param $src
     * @param bool $use_image_proxy
     * @param bool $local_path
     *
     * @return mixed
     */
    public static function toMedia($src, bool $use_image_proxy = false, bool $local_path = false): string
    {
        if (empty(_W('attachurl'))) {
            We7::load()->model('attachment');
            setW('attachurl', We7::attachment_set_attach_url());
        }
        $res = We7::tomedia($src, $local_path);
        if (!$local_path) {
            $str = ['/addons/'.APP_NAME];
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
        $full_path = ATTACHMENT_ROOT.$dirname.$filename;

        if (!is_dir(ATTACHMENT_ROOT.$dirname)) {
            We7::make_dirs(ATTACHMENT_ROOT.$dirname);
        }

        return $full_path;
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
    public static function getRequireAccounts(
        deviceModelObj $device,
        userModelObj $user,
        accountModelObj $account,
        array $excepts = []
    ): array {
        $accounts = [];

        //获取多个关注公众号设置
        $qr_codes = $account->get('qrcodesData', []);
        if ($qr_codes && is_array($qr_codes)) {
            $accounts = $qr_codes;
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
        $visited_accounts = $user->getLastActiveData('accounts', []);
        $visited_accounts = is_array($visited_accounts) ? $visited_accounts : [];

        //排除
        if ($excepts) {
            $excepts = is_array($excepts) ? $excepts : [$excepts];
            foreach ($excepts as $uid) {
                if ($uid) {
                    $visited_accounts[$uid] = time();
                }
            }

            $user->setLastActiveData('accounts', $visited_accounts);
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
                    'openid' => $entry->getOpenid(),
                    'nickname' => $entry->getNickname(),
                    'avatar' => $entry->getAvatar(),
                    '_obj' => $entry,
                ];
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
                    'uid' => $entry->getUid(),
                    'bonus_type' => $entry->getBonusType(),
                    'title' => $entry->getTitle(),
                    'descr' => $entry->getDescription(),
                    'img' => $entry->getImg(),
                    'qrcode' => $entry->getQrcode(),
                    'clr' => $entry->getClr(),
                    '_obj' => $entry,
                ];
            } elseif (is_array($entry)) {
                foreach ($entry as $key => $val) {
                    setArray($data, $key, $val);
                }
            }
        }

        return $data;
    }

    public static function generateUID(): string
    {
        return getmypid().'-'.time().'-'.Util::random(6, true);
    }

    /**
     * @param $length
     * @param bool $numeric
     *
     * @return string
     */
    public static function random($length, bool $numeric = false): string
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

        return '#'.implode('', $arr);
    }

    public static function buildUrl($parsed_url): string
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'].'://' : '';
        $host = $parsed_url['host'] ?? '';
        $port = isset($parsed_url['port']) ? ':'.$parsed_url['port'] : '';
        $user = $parsed_url['user'] ?? '';
        $pass = isset($parsed_url['pass']) ? ':'.$parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parsed_url['path'] ?? '';
        $query = isset($parsed_url['query']) ? '?'.$parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#'.$parsed_url['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    /**
     * @param string $do
     * @param array $params
     * @param bool $eid
     * @return string
     */
    public static function url(string $do = '', array $params = [], bool $eid = true): string
    {
        $params['m'] = APP_NAME;

        if ($eid && Request::isset('eid')) {
            $params['eid'] = Request::int('eid');
        }

        return We7::url("site/entry/$do", $params);
    }

    /**
     * @param string $do
     * @param array $params
     * @param bool $full_url
     * @return string
     */
    public static function murl(string $do = '', array $params = [], bool $full_url = true): string
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

        $str[] = 'addons/'.APP_NAME.'/';
        $str[] = 'payment/';

        if ($full_url) {
            $url = _W('siteroot').'app/'.$url;
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

    public static function exportCSVToFile($filename, array $header = [], array $data = [])
    {
        We7::make_dirs(dirname($filename));

        if (!file_exists($filename)) {
            $file = fopen($filename, 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $header);
        } else {
            $file = fopen($filename, 'a');
        }

        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
    }

    /**
     * 导出excel.
     *
     * @param string $name
     * @param array $header
     * @param array $data
     */
    public static function exportCSV(string $name = '', array $header = [], array $data = [])
    {
        $serial = date('YmdHis');
        $name = "{$name}_$serial.csv";
        $dirname = "export/data/";
        $full_filename = Util::getAttachmentFileName($dirname, $name);

        self::exportCSVToFile($full_filename, $header, $data);

        Response::redirect(self::toMedia("$dirname$name"));
    }

    public static function getWe7Material($typename, $page, $page_size = DEFAULT_PAGE_SIZE): array
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

    public static function parseIdsFromGPC(): array
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

    /**
     * 获取指定图片的由压缩服务器代理后的URL
     * @param $image_url
     * @return string
     */
    public static function getImageProxyURL($image_url): string
    {
        if (empty($image_url)) {
            return '';
        }

        $url = App::getImageProxyURL();
        if (empty($url)) {
            return $image_url;
        }

        $signStr = '';

        $secret = App::getImageProxySecretKey();
        if ($secret) {
            $signStr = ',s'.strtr(base64_encode(hash_hmac('sha256', $image_url, $secret, true)), '+/', '-_');
            $url = rtrim($url, '\\/');
        }

        return "$url$signStr/$image_url";
    }

    /**
     * 返回省份列表
     * @return string[]
     */
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

    public static function isSysLoadAverageOk(): bool
    {
        $load = sys_getloadavg();

        return $load === false || $load[0] < SYS_MAX_LOAD_AVERAGE_VALUE;
    }

    public static function getSettingsNavs(): array
    {
        $navs = [
            'device' => '设备',
            'user' => '用户',
            'agent' => '代理商',
            'wxapp' => '小程序',
            'commission' => '佣金',
            'balance' => '积分',
            'account' => '任务',
            'notice' => '通知',
            'payment' => '支付',
            'misc' => '其它',
            'upgrade' => '系统升级',
        ];

        if (!App::isBalanceEnabled()) {
            unset($navs['balance']);
        }

        return $navs;
    }

    public static function getAndCheckWithdraw($id)
    {
        /** @var commission_balanceModelObj $balance_obj */
        $balance_obj = CommissionBalance::findOne(['id' => $id, 'src' => CommissionBalance::WITHDRAW]);
        if (empty($balance_obj)) {
            return err('操作失败，请刷新页面后再试！');
        }

        $openid = $balance_obj->getOpenid();
        $user = User::get($openid, true);
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        if (!$user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
            return err('用户无法锁定，请重试！');
        }

        if ($balance_obj->getUpdatetime()) {
            $state = $balance_obj->getExtraData('state');
            if ($state === 'mchpay') {
                $MCHPayResult = $balance_obj->getExtraData('mchpayResult');
                if (empty($MCHPayResult['payment_no']) && $MCHPayResult['detail_status'] === 'FAIL') {
                    return $balance_obj;
                }
            }

            return err('操作失败，请刷新页面后再试！');
        }

        return $balance_obj;
    }

    /**
     * 获取返回js sdk字符串.
     *
     * @param bool $debug
     *
     * @return string
     */
    public static function jssdk(bool $debug = false): string
    {
        ob_start();

        We7::register_jssdk($debug);

        return ob_get_clean();
    }
}
