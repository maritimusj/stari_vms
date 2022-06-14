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

//初始化事件驱动
EventBus::init();

//设置httpClient
$http_client = new we7HttpClient();

CtrlServ::setHttpClient($http_client);
ChargingServ::setHttpClient($http_client);

We7::load()->func('cache');

//启动应用
try {
    app()->run();
} catch (Exception $e) {
    Log::error("app", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
