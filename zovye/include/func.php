<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTimeInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use zovye\base\ClassLoader;
use zovye\base\Model;
use zovye\base\ModelFactory;
use zovye\base\ModelObj;

/**
 * 返回全局唯一的APP
 */
function app(): WeApp
{
    static $app = null;
    if (empty($app)) {
        $app = new WeApp();
    }

    return $app;
}

/**
 * 加载指定的数据模型类
 */
function m(string $name): ModelFactory
{
    static $loader = null;
    if (empty($loader)) {
        $loader = new Model();
    }

    if (empty($name)) {
        trigger_error('module name is empty', E_USER_ERROR);
    }

    $model = $loader->load($name);

    if (empty($model)) {
        trigger_error('module object is null', E_USER_ERROR);
    }

    return $model;
}

function tb(string $name): string
{
    return APP_NAME.'_'.$name;
}

/**
 * 获取系统相关设置
 */
class __ZOVYE_SETTINGS__
{
    static $cache = [];
}

function settings(string $key = '', $default = null)
{
    if (!isset(__ZOVYE_SETTINGS__::$cache[$key])) {
        __ZOVYE_SETTINGS__::$cache[$key] = app()->settings($key, $default);
    }

    return __ZOVYE_SETTINGS__::$cache[$key];
}

function updateSettings(string $key, $val): bool
{
    unset(__ZOVYE_SETTINGS__::$cache[$key]);

    return app()->updateSettings($key, $val);
}

/**
 * 其它全局设置
 */
class __ZOVYE_CONFIG__
{
    static $cache = [];
}

function globalConfig(string $name, $path = '', $default = null)
{
    if (empty($name)) {
        return $default;
    }

    if (!isset(__ZOVYE_CONFIG__::$cache[$name])) {
        __ZOVYE_CONFIG__::$cache[$name] = app()->get($name, []);
    }

    return getArray(__ZOVYE_CONFIG__::$cache[$name], $path, $default);
}

function updateGlobalConfig(string $name, $path, $val): bool
{
    if (empty($name)) {
        return false;
    }

    unset(__ZOVYE_CONFIG__::$cache[$name]);

    $config = app()->get($name, []);
    setArray($config, $path, $val);

    return app()->set($name, $config);
}

/**
 * 下划线转驼峰
 */
function toCamelCase($str): string
{
    $str = preg_replace_callback('/([-_]+([a-z]))/i', function ($matches) {
        return strtoupper($matches[2]);
    }, $str);

    return strval($str);
}

/**
 * 驼峰转下划线
 */
function toSnakeCase($str): string
{
    $str = str_replace('_', '', $str);
    $str = preg_replace_callback('/([A-Z])/', function ($matches) {
        return '_'.strtolower($matches[0]);
    }, $str);

    return ltrim($str, '_');
}

/**
 * 返回默认值
 */
function ifEmpty($data, $default, $convert = true)
{
    if (!isset($data) && isset($default)) {
        if (is_callable($default)) {
            return call_user_func($default);
        }

        return $default;
    }

    return $convert ? convert($data, gettype($default)) : $data;
}

function convert($val, $type_hints)
{
    switch ($type_hints) {
        case 'int':
        case 'integer':
            return intval($val);
        case 'string':
        case 'str':
            return strval($val);
        case 'bool':
        case 'boolean':
            return boolval($val);
        case 'float':
            return floatval($val);
        case 'double':
            return doubleval($val);
        case 'array':
            return (array)$val;
        case 'json':
            return json_decode($val, true);
        default:
            return $val;
    }
}

/**
 *  更新数组中指定值，可以使用key.sub.child格式指定键
 */
function setArray(array &$array, $key, $val = null): array
{
    if (empty($key)) {
        $array = $val;
    } else {
        if (is_scalar($key) && isset($array[$key])) {
            $array[$key] = $val;
        } else {
            $keys = is_array($key) ? $key : explode('.', $key);

            $arr = &$array;
            if (!is_array($arr)) {
                $arr = [];
            }

            $key_name = array_pop($keys);

            foreach ($keys as $sub_key) {
                if ($sub_key === '') {
                    continue;
                }
                if (!isset($arr[$sub_key]) || !is_array($arr[$sub_key])) {
                    $arr[$sub_key] = [];
                }
                $arr = &$arr[$sub_key];
            }

            if (empty($arr) || !is_array($arr)) {
                $arr = [$key_name => $val];
            } else {
                $arr[$key_name] = $val;
            }
        }
    }

    return $array;
}

/**
 * 获取数组指定路径的值
 */
function getArray($array, $key = '', $default = null, $convert = true)
{
    if (!is_array($array) || empty($key)) {
        return ifEmpty($array, $default, $convert);
    }

    if (is_scalar($key) && isset($array[$key])) {
        return ifEmpty($array[$key], $default, $convert);
    }

    $val = $array;
    $keys = is_array($key) ? $key : explode('.', $key);

    for ($i = 0; $i < count($keys); $i++) {
        $sub_key = $keys[$i];
        if ($sub_key !== '' && isset($val[$sub_key])) {
            $val = $val[$sub_key];
        } else {
            return $default;
        }
    }

    return ifEmpty($val, $default, $convert);
}

/**
 * 判断数组是否是个全空数组
 */
function isEmptyArray($arr): bool
{
    if (empty($arr)) {
        return true;
    }

    if (!is_array($arr)) {
        return false;
    }

    foreach ($arr as $item) {
        if ($item && !is_array($item)) {
            return false;
        }
        if (!isEmptyArray($item)) {
            return false;
        }
    }

    return true;
}

function error(int $errno, string $message = ''): array
{
    return [
        'errno' => $errno,
        'message' => $message,
    ];
}

function err(string $message = ''): array
{
    return [
        'errno' => State::ERROR,
        'message' => $message,
    ];
}

function is_error($data): bool
{
    return is_array($data) && isset($data['errno']) && $data['errno'] != 0;
}


function load(): ClassLoader
{
    return new ClassLoader();
}

function url($tag, ...$names)
{
    $module_url = App::isHttpsWebsite() ? str_replace('http://', 'https://', MODULE_URL) : MODULE_URL;
    foreach ($names as $name) {
        if (substr($name, 0, 4) !== 'http') {
            $name = $module_url.$name;
        }
        if ($tag) {
            if (substr($name, strpos($name, '?') - 4, 4) === '.css') {
                echo "<link  rel=\"stylesheet\" type=\"text/css\" href=\"$name\">\r\n";
            } else {
                echo "<script src=\"$name\"></script>\r\n";
            }
        } else {
            echo $name;
        }
    }
}

if (!function_exists('array_key_first')) {
    function array_key_first(array $arr)
    {
        foreach ($arr as $key => $unused) {
            return $key;
        }

        return null;
    }
}

if (!function_exists('strexists')) {
    function strexists($str, $search): bool
    {
        return !(false === strpos($str, $search));
    }
}

function hashFN(callable $fn, ...$val): string
{
    try {
        $ref = new ReflectionFunction($fn);
    } catch (ReflectionException $e) {
        try {
            $ref = new ReflectionMethod($fn);
        } catch (ReflectionException $e) {
            trigger_error($e->getMessage());
        }
    }
    if (!empty($ref)) {
        $data = [
            $ref->getFileName(),
            $ref->getStartLine(),
            $ref->getEndLine(),
            $ref->getName(),
        ];
        foreach ($val as $v) {
            if ($v instanceof DateTimeInterface) {
                $data[] = 'datetime:'.$v->getTimestamp();
            } elseif ($v instanceof ModelObj) {
                $data[] = get_class($v).':'.$v->getId();
            } elseif (is_scalar($v)) {
                $data[] = strval($v);
            } elseif (is_array($v)) {
                $data[] = http_build_query($v);
            } else {
                $data[] = json_encode($v);
            }
        }

        return md5(implode(':', $data));
    }

    trigger_error('无法识别的函数或方法!');

    return '';
}

function onceCall(callable $fn, ...$params)
{
    static $cache = [];

    $v = hashFN($fn, ...$params);
    if (!isset($cache[$v])) {
        $result = $fn(...$params);
        $cache[$v] = $result;

        return $result;
    }

    return $cache[$v];
}

function _W(string $name, $default = null)
{
    return getArray($GLOBALS['_W'], $name, $default);
}

function setW(string $name, $val)
{
    setArray($GLOBALS['_W'], $name, $val);
}

function request(string $name, $default = null)
{
    return getArray($GLOBALS['_GPC'], $name, $default);
}