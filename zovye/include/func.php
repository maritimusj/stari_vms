<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use zovye\base\model;
use zovye\base\modelFactory;

/**
 * 返回全局唯一的APP
 * @return WeApp
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
 * @param string $name
 * @return modelFactory
 */
function m(string $name): modelFactory
{
    static $loader = null;
    if (empty($loader)) {
        $loader = new model();
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
    return APP_NAME . '_' . $name;
}

/**
 * 获取系统相关设置
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
class __ZOVYE_SETTINGS__
{
    static array $cache = [];
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
 * @param string $name
 * @param string $path
 * @param null $default
 * @return mixed|null
 */
class __ZOVYE_CONFIG__
{
    static array $cache = [];
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
 * @param $str
 * @return string
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
 * @param $str
 * @return string
 */
function toSnakeCase($str): string
{
    $str = str_replace('_', '', $str);
    $str = preg_replace_callback('/([A-Z])/', function ($matches) {
        return '_' . strtolower($matches[0]);
    }, $str);
    return ltrim($str, '_');
}

/**
 * 返回默认值
 * @param mixed $data
 * @param mixed $default
 * @return mixed
 */
function ifEmpty($data, $default)
{
    if (!isset($data) && isset($default)) {
        if (is_callable($default)) {
            return $default();
        }

        return $default;
    }

    return $data;
}

/**
 *  更新数组中指定值，可以使用key.sub.child格式指定键
 * @param array $array
 * @param string|array $key
 * @param mixed $val
 * @return array
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
 * @param mixed $array
 * @param string|array $key
 * @param mixed $default
 * @return mixed
 */
function getArray($array, $key = '', $default = null)
{
    if (!is_array($array) || empty($key)) {
        return ifEmpty($array, $default);
    }

    if (is_scalar($key) && isset($array[$key])) {
        return $array[$key];
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

    return ifEmpty($val, $default);
}

/**
 * 判断数组是否是个全空数组
 * @param mixed $arr
 * @return bool
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

/**
 * @param int $errno
 * @param string $message
 * @return array
 */
function error(int $errno, string $message = ''): array
{
    return array(
        'errno' => $errno,
        'message' => $message,
    );
}

function err(string $message = ''): array
{
    return array(
        'errno' => State::ERROR,
        'message' => $message,
    );
}

/**
 * @param mixed $data
 * @return bool
 */
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
            $name = $module_url . $name;
        }
        if ($tag) {
            if (substr($name, strpos($name, '?') - 4, 4) === '.css') {
                echo "<link  rel=\"stylesheet\" type=\"text/css\" href=\"{$name}\">\r\n";
            } else {
                echo "<script src=\"{$name}\"></script>\r\n";
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
        return NULL;
    }
}

function hashFN(callable $fn, ...$val): string
{
    try {
        $ref = new ReflectionFunction($fn);
    }catch (ReflectionException $e) {
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
            $data[] = strval($v);
        }
        return md5(implode(':', $data));
    }

    trigger_error('无法识别的函数或方法!');
    return '';
}

function onceCall(callable $fn, ...$val)
{
    static $cache = [];
    $v = hashFN($fn, ...$val);
    if (!isset($cache[$v])) {
        $cache[$v] = $fn();
    }
    return $cache[$v];
}