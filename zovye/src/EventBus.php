<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use Exception;
use ReflectionClass;
use ReflectionMethod;

class EventBus
{
    static $events_data = [

    ];

    /**
     * 初始化事件处理器
     * @throws
     */
    public static function init()
    {
        $events = settings(
            'events',
            [
                'device' =>
                    [
                        'AccountEventHandler', //公众号检查
                        'BalanceEventHandler', //余额处理
                        'CommissionEventHandler', //处理佣金
                        'AgentBonusEventHandler', //佣金奖励
                        'LocationEventHandler', //定位检查
                        'We7CreditEventHandler', //处理微擎积分
                        'JobEventHandler', //订单后续处理Job
                        'VoucherEventHandler', //提货券
                    ],
            ]
        );

        if (empty($events) || !is_array($events)) {
            return;
        }

        foreach ($events as $type => $classes) {
            if (!isset(self::$events_data[$type])) {
                self::$events_data[$type] = [];
            }

            foreach ($classes as $classname) {
                include_once ZOVYE_CORE_ROOT . 'event_handler' . DIRECTORY_SEPARATOR . $classname . '.php';

                $classname = __NAMESPACE__ . '\\' . $classname;
                if (class_exists($classname)) {

                    $reflection = new ReflectionClass($classname);
                    $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
                    $prefix = 'on' . ucfirst($type);
                    $n = strlen($prefix);

                    foreach ($methods as $m) {
                        if (substr($m->name, 0, $n) == $prefix) {
                            self::$events_data[$type][$m->name][] = $m->class;
                        }
                    }
                }
            }
        }
    }

    /**
     * 通知处理程序进行事件处理
     * @param $name
     * @param array $params
     * @throws Exception
     */
    public static function on($name, array $params = [])
    {
        list($type, $event) = explode('.', strval($name), 2);

        if ($type && $event) {
            $method_name = 'on' . ucfirst($type) . ucfirst($event);
            $handlers = getArray(self::$events_data, "{$type}.{$method_name}");
            if ($handlers && is_array($handlers)) {
                foreach ($handlers as $clazz) {
                    self::handle($clazz, $method_name, $params);
                }
            }
        }
    }

    /**
     * @param $classname
     * @param $method_name
     * @param array $params
     * @throws
     */
    protected static function handle($classname, $method_name, array $params = [])
    {
        $reflection = new ReflectionMethod($classname, $method_name);

        $args = [];
        foreach ($reflection->getParameters() as $arg) {
            $type = $arg->getType();
            /** getName() is an undocumented method */
            $args[] = self::match($params, new ReflectionClass($type->getName()));
        }

        $res = $reflection->invokeArgs(null, $args);

        if (is_error($res)) {
            throw new Exception($res['message'], $res['errno']);
        }
    }

    /**
     * @param array $params
     * @param ReflectionClass $class
     * @return mixed
     */
    protected static function match(array $params, ReflectionClass $class)
    {
        if ($class == null) {
            return null;
        }
        
        foreach ($params as $entry) {
            if (is_object($entry) && $class->name == get_class($entry)) {
                return $entry;
            }
        }

        foreach ($params as $entry) {
            if (is_object($entry) && $class->isSubclassOf(get_class($entry))) {
                return $entry;
            }
        }

        foreach ($params as $entry) {
            if (is_object($entry) && $class->isInterface() && ($entry instanceof $class->name)) {
                return $entry;
            }
        }

        return null;
    }
}
