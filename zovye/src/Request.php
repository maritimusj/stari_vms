<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class Request
{
    private static $data = [];

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

    public static function setData($data = [])
    {
        self::$data = $data;
    }

    public static function setDefault(string $key, $data)
    {
        setArray(self::$data, $key, $data);
    }

    public static function json(string $key = '', $default = [])
    {
        static $data = null;

        if (!isset($data)) {
            $res = json_decode(self::raw(), true);
            $data = $res ?: [];
        }

        return getArray($data, $key, $default);
    }

    public static function isset(string $name): bool
    {
        return isset(self::$data[$name]);
    }

    public static function has(string $name): bool
    {
        return !empty(self::$data[$name]);
    }

    public static function is_string(string $name): bool
    {
        return is_string(self::$data[$name]);
    }

    public static function is_numeric(string $name): bool
    {
        return is_numeric(self::$data[$name]);
    }

    public static function is_array(string $name): bool
    {
        return is_array(self::$data[$name]);
    }

    public static function all(): array
    {
        return self::$data;
    }

    public static function raw(): string
    {
        $result = null;

        if (!isset($result)) {
            $result = file_get_contents('php://input');
            if ($result === false) {
                $result = '';
            }
        }

        return $result;
    }

    public static function header($name)
    {
        return $_SERVER[strtoupper($name)];
    }

    public static function is_ajax(): bool
    {
        return boolval(_W('isajax'));
    }

    public static function is_post(): bool
    {
        return boolval(_W('ispost'));
    }

    public static function is_get(): bool
    {
        return !self::is_post();
    }

    public static function int(string $name, int $default = 0): int
    {
        return getArray(self::$data, $name, $default);
    }

    public static function float(string $name, float $default = 0.0, int $precision = -1): float
    {
        $v = getArray(self::$data, $name, $default);

        return $precision > -1 ? round($v, $precision) : $v;
    }

    public static function bool(string $name, bool $default = false): bool
    {
        $v = getArray(self::$data, $name, $default, false);
        if (empty($v)) {
            return false;
        }
        if (is_string($v) && strtolower($v) === 'false') {
            return false;
        }
        return boolval($v);
    }

    public static function str(string $name, string $default = '', $url_decode = false): string
    {
        $str = getArray(self::$data, $name, $default);
        if ($url_decode) {
            $str = urldecode($str);
        }

        return $str;
    }

    public static function trim(string $name, string $default = '', $url_decode = false): string
    {
        return trim(self::str($name, $default, $url_decode)) ?: $default;
    }

    public static function array(string $name, array $default = []): array
    {
        return getArray(self::$data, $name, $default);
    }

    public static function op(string $default = ''): string
    {
        return self::str('op', $default);
    }
}
