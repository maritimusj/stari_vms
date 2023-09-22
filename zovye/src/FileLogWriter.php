<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use DateTime;
use zovye\contract\ILogWriter;

class FileLogWriter implements ILogWriter
{
    static $log_cache = [];

    static $suffix = [
        L_ALL => '',
        L_DEBUG => 'debug',
        L_INFO => 'info',
        L_WARN => 'warn',
        L_ERROR => 'error',
        L_FATAL => 'fatal',
    ];

    public function write($level, $topic, $data)
    {
        if (empty(self::$log_cache)) {
            register_shutdown_function(function () use ($topic) {
                foreach (self::$log_cache as $filename => $data) {
                    if ($filename && $data) {
                        file_put_contents($filename, $data, FILE_APPEND);
                    }
                }

                self::$log_cache = [];

                if (rand(0, 10) == 10) {
                    self::deleteExpiredLogFiles($topic, LOG_MAX_DAY);
                }
            });
        }

        $log_filename = self::logFileName($topic, self::$suffix[$level] ?? '');

        ob_start();

        echo PHP_EOL."-----------------------------".date(
                'Y-m-d H:i:s'
            ).' [ '.REQUEST_ID." ]---------------------------------------".PHP_EOL;

        print_r($data);

        echo PHP_EOL;

        self::$log_cache[$log_filename][] = ob_get_clean();
    }

    /**
     * 创建并返回日志目录
     * @param string $name
     * @return string
     */
    public static function logDir(string $name): string
    {
        $log_dir = LOG_DIR.App::uid(8).DIRECTORY_SEPARATOR.$name;

        We7::make_dirs($log_dir);

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

        $filename = $log_dir.DIRECTORY_SEPARATOR.date('Ymd');
        if ($suffix) {
            $filename .= ".$suffix";
        }

        $filename .= '.log';

        return $filename;
    }

    public static function deleteExpiredLogFiles(string $name, int $keep_days): void
    {
        $files = [];
        $patten = self::logDir($name).'/*.log';
        foreach (glob($patten) as $filename) {
            if (is_file($filename)) {
                $files[basename($filename, '.log')] = $filename;
            }
        }
        $date = new DateTime();
        do {
            $time = $date->format('Ymd');
            unset($files[$time]);
            foreach (self::$suffix as $suffix) {
                unset($files["$time.$suffix"]);
            }
            $date->modify('-1 day');
        } while (--$keep_days > 0);

        foreach ($files as $filename) {
            unlink($filename);
        }
    }
}