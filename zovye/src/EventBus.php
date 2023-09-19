<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use zovye\event\AccountEventHandler;
use zovye\event\AgentBonusEventHandler;
use zovye\event\CommissionEventHandler;
use zovye\event\GoodsQuotaEventHandler;
use zovye\event\JobEventHandler;
use zovye\event\LocationEventHandler;
use zovye\event\VoucherEventHandler;
use zovye\event\We7CreditEventHandler;

class EventBus
{
    const BEFORE_LOCK = 'device.beforeLock';
    const LOCKED = 'device.locked';
    const OPEN_SUCCESS = 'device.openSuccess';
    const OPEN_FAIL = 'device.openFail';
    const ORDER_CREATED = 'device.orderCreated';

    static $events_data = [];

    /**
     * 初始化事件处理器
     * @throws
     */
    public static function init()
    {
        $events = [
            'device' =>
                [
                    AccountEventHandler::class, //公众号检查
                    CommissionEventHandler::class, //处理佣金
                    AgentBonusEventHandler::class, //佣金奖励
                    LocationEventHandler::class, //定位检查
                    We7CreditEventHandler::class, //处理微擎积分
                    JobEventHandler::class, //订单后续处理Job
                    VoucherEventHandler::class, //提货券
                    GoodsQuotaEventHandler::class,//商品限额
                ],
        ];

        foreach ($events as $w => $classes) {
            if (!isset(self::$events_data[$w])) {
                self::$events_data[$w] = [];
            }
            foreach ($classes as $classname) {
                $reflection = new ReflectionClass($classname);
                $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
                $prefix = 'on'.ucfirst($w);
                foreach ($methods as $method) {
                    if (substr($method->name, 0, strlen($prefix)) == $prefix) {
                        self::$events_data[$w][$method->name][] = $method->class;
                    }
                }
            }
        }
    }

    /**
     * 通知处理程序进行事件处理
     * @throws Exception
     */
    public static function on($name, array $params = [])
    {
        list($w, $event) = explode('.', strval($name), 2);

        if ($w && $event) {
            $method_name = 'on'.ucfirst($w).ucfirst($event);
            $handlers = getArray(self::$events_data, "$w.$method_name");
            if ($handlers && is_array($handlers)) {
                foreach ($handlers as $clazz) {
                    self::handle($clazz, $method_name, $params);
                }
            }
        }
    }

    /**
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

    protected static function match(array $params, ReflectionClass $class)
    {
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
            if ($class->isInterface() && ($entry instanceof $class->name)) {
                return $entry;
            }
        }

        return null;
    }
}
