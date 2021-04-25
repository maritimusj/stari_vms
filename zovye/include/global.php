<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

//捕获错误和异常
use Exception;

define('REQUEST_ID', getmypid() . '-' . time() . '-' . Util::random(6, true));

Util::setErrorHandler();

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
