<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;

class Log
{
    public static $suffix = [
        L_ALL => '',
        L_DEBUG => 'debug',
        L_INFO => 'info',
        L_WARN => 'warn',
        L_ERROR => 'error',
        L_FATAL => 'fatal',
    ];

    public static function append($level, $topic, $data = [])
    {
        if ($level >= LOG_LEVEL && (empty(LOG_TOPIC_INCLUDES) || in_array($topic, LOG_TOPIC_INCLUDES))) {
            self::logToFile($topic, $data, true, self::$suffix[$level] ?? '');
        }
    }

    public static function error($topic, $data = [])
    {
        self::append(L_ERROR, $topic, $data);
    }

    /**
     * @param $topic
     * @param mixed $data
     * @return never-return
     */
    public static function fatal($topic, $data = [])
    {
        self::append(L_FATAL, $topic, $data);
        if (defined("IN_JOB")) {
            Job::exit();
        }
        exit();
    }

    public static function warning($topic, $data = [])
    {
        self::append(L_WARN, $topic, $data);
    }

    public static function info($topic, $data = [])
    {
        self::append(L_INFO, $topic, $data);
    }

    public static function debug($topic, $data = [])
    {
        self::append(L_DEBUG, $topic, $data);
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
     * @param string $suffix
     * @return string
     */
    public static function logFileName(string $name, string $suffix = ''): string
    {
        $log_dir = self::logDir($name);
        $filename = $log_dir . DIRECTORY_SEPARATOR . date('Ymd');
        if ($suffix) {
            $filename .= ".$suffix";
        }
        $filename .= '.log';
        return $filename;
    }

    public static function deleteExpiredLogFiles(string $name, int $keep_days): void
    {
        $files = [];
        $patten = self::logDir($name) . '/*.log';
        foreach (glob($patten) as $filename) {
            if (is_file($filename)) {
                $files[basename($filename, '.log')] = $filename;
            }
        }
        $date = new DateTime();
        do {
            $time = $date->format('Ymd');
            unset($files[$time]);
            foreach (Log::$suffix as $suffix) {
                unset($files["$time.$suffix"]);
            }
            $date->modify('-1 day');
        } while (--$keep_days > 0);

        foreach ($files as $filename) {
            unlink($filename);
        }
    }

    static $log_cache = [];

    /**
     * 输出指定变量到文件中
     * @param string $name 日志名称
     * @param mixed $data 数据
     * @param bool $force
     * @param string $suffix
     * @return bool
     */
    public static function logToFile(string $name, $data, bool $force = false, string $suffix = ''): bool
    {
        if (DEBUG || $force) {
            if (empty(self::$log_cache)) {
                register_shutdown_function(function () use ($name) {
                    foreach (self::$log_cache as $filename => $data) {
                        if ($filename && $data) {
                            file_put_contents($filename, $data, FILE_APPEND);
                        }
                    }
                    self::$log_cache = [];
                    if (rand(0, 10) == 10) {
                        self::deleteExpiredLogFiles($name, LOG_MAX_DAY);
                    }
                });
            }

            $log_filename = self::logFileName($name, $suffix);

            ob_start();

            echo PHP_EOL . "-----------------------------" . date('Y-m-d H:i:s') . ' [ ' . REQUEST_ID . " ]---------------------------------------" . PHP_EOL;

            print_r($data);

            echo PHP_EOL;

            self::$log_cache[$log_filename][] = ob_get_clean();
        }

        return true;
    }
}