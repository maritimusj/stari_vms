<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

//捕获错误和异常
use Exception;

define('REQUEST_ID', Util::generateUID());

Util::setErrorHandler();

//设置日志等级
Log::$level = LOG_LEVEL;

//初始化事件驱动
EventBus::init();

//设置httpClient
CtrlServ::setHttpClient(new we7HttpClient());

We7::load()->func('cache');

//启动应用
try {
    app()->run();
} catch (Exception $e) {
    Util::logToFile("app", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
