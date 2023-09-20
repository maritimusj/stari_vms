<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\contract\ILogWriter;

class Log
{
    public static $log_level;

    /** @var ILogWriter */
    public static $writer;

    public static function init(ILogWriter $writer, $level)
    {
        self::$writer = $writer;
        self::$log_level = $level;
    }

    public static function append($level, $topic, $data)
    {
        if (self::$writer != null
            && $level >= self::$log_level
            && (empty(LOG_TOPIC_INCLUDES) || in_array($topic, LOG_TOPIC_INCLUDES))) {
            if (is_callable($data)) {
                $data = call_user_func($data);
            }
            self::$writer->write($level, $topic, $data);
        }
    }

    public static function error($topic, $data)
    {
        self::append(L_ERROR, $topic, $data);
    }

    /**
     * @param mixed $data
     */
    public static function fatal($topic, $data)
    {
        self::append(L_FATAL, $topic, $data);
        if (defined("IN_JOB")) {
            Job::exit();
        }
        exit();
    }

    public static function warning($topic, $data)
    {
        self::append(L_WARN, $topic, $data);
    }

    public static function info($topic, $data)
    {
        self::append(L_INFO, $topic, $data);
    }

    public static function debug($topic, $data)
    {
        self::append(L_DEBUG, $topic, $data);
    }
}