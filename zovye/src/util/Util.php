<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\util;

use DateTime;
use Throwable;
use zovye\App;
use zovye\Log;
use zovye\Request;
use zovye\Response;
use zovye\We7;
use function zovye\_W;
use function zovye\getArray;
use function zovye\settings;
use function zovye\setW;

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
     * 检查指定trait是否可用
     *
     * @param mixed $class 要检查的类
     * @param string $traitName trait名称
     *
     */
    public static function traitUsed($class, string $traitName): bool
    {
        $traits = (array)class_uses($class);
        foreach (class_parents($class) as $classname) {
            $traits = array_merge($traits, (array)class_uses($classname));
        }

        return $traits && in_array($traitName, $traits);
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


    /**
     * 格式化时间
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
     * 返回指定频道的平台相关值
     */
    public static function encryptTopic(string $name = 'all'): string
    {
        static $app_key = null;
        if (is_null($app_key)) {
            $app_key = settings('ctrl.appKey', '');
        }

        return md5("$app_key$name");
    }

    /**
     * 修正微擎payResult回调时，toMedia函数工作不正常的问题
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

    public static function generateUID(): string
    {
        return getmypid().'-'.time().'-'.Util::random(6, true);
    }

    public static function random($length, bool $numeric = false): string
    {
        return We7::random($length, $numeric);
    }

    public static function flock($uid, callable $fn)
    {
        $dir = DATA_DIR.'locker'.DIRECTORY_SEPARATOR;
        We7::make_dirs($dir);

        $filename = $dir.sha1($uid).'.lock';

        $fp = fopen($filename, 'w+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                if (DEBUG) {
                    fwrite($fp, REQUEST_ID."\r\n");
                    fwrite($fp, date('Y-m-d H:i:s')."\r\n");
                    fwrite($fp, $uid."\r\n");
                }
                if ($fn) {
                    $result = call_user_func($fn);
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            if (file_exists($filename)) {
                unlink($filename);
            }
        }

        return $result ?? null;
    }

    /**
     * 随机颜色值
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

    public static function url(string $do = '', array $params = [], bool $eid = true): string
    {
        $params['m'] = APP_NAME;

        if ($eid && Request::isset('eid')) {
            $params['eid'] = Request::int('eid');
        }

        return We7::url("site/entry/$do", $params);
    }

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
        return Util::murl($do, $params);
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
     * 导出csv
     */
    public static function exportCSV(string $name = '', array $header = [], array $data = [])
    {
        $serial = date('YmdHis');
        $name = "{$name}_$serial.csv";
        $dirname = "export/data/";
        $full_filename = Helper::getAttachmentFileName($dirname, $name);

        self::exportCSVToFile($full_filename, $header, $data);

        Response::redirect(self::toMedia("$dirname$name"));
    }

    /**
     * 获取指定图片的由压缩服务器代理后的URL
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

    public static function isSysLoadAverageOk(): bool
    {
        $load = sys_getloadavg();

        return $load === false || $load[0] < SYS_MAX_LOAD_AVERAGE_VALUE;
    }

    /**
     * 获取返回js sdk字符串
     */
    public static function jssdk(bool $debug = false): string
    {
        ob_start();

        We7::register_jssdk($debug);

        return ob_get_clean();
    }
}
