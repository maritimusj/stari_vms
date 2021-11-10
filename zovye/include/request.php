<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

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

class request
{
    public static function json(string $key = '', $default = null)
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
        return isset($GLOBALS['_GPC'][$name]);
    }

    public static function has(string $name): bool
    {
        return !empty($GLOBALS['_GPC'][$name]);
    }

    public static function is_string(string $name): bool
    {
        return is_string($GLOBALS['_GPC'][$name]);
    }

    public static function is_numeric(string $name): bool
    {
        return is_numeric($GLOBALS['_GPC'][$name]);
    }

    public static function is_array(string $name): bool
    {
        return is_array($GLOBALS['_GPC'][$name]);
    }

    public static function all(): array
    {
        return (array)$GLOBALS['_GPC'];
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
        return $_SERVER[$name];
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
        return intval(request($name, $default));
    }

    public static function float(string $name, float $default = 0, int $precision = -1): float
    {
        $v = floatval(request($name, $default));
        return $precision > -1 ? round($v, $precision) : $v;
    }

    public static function bool(string $name, bool $default = false): bool
    {
        return boolval(request($name, $default));
    }

    public static function str(string $name, string $default = '', $url_decode = false): string
    {
        $str = strval(request($name, $default));
        if ($url_decode) {
            $str = urldecode($str);
        }
        return $str;
    }

    public static function trim(string $name, string $default = '', $url_decode = false): string
    {
        return trim(request::str($name, $default, $url_decode)) ?: $default;
    }

    public static function array(string $name, array $default = []): array
    {
        return is_array($GLOBALS['_GPC'][$name]) ? $GLOBALS['_GPC'][$name] : $default;
    }

    public static function op(string $default = ''): string
    {
        return self::str('op', $default);
    }
}
