<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;

//定义常量REQUEST_ID
define('REQUEST_ID', Util::generateUID());

//捕获错误和异常
Util::setErrorHandler();

try {
    Util::extraAjaxJsonData();
    
    //设置request数据来源
    request::setData($GLOBALS['_GPC']);

    //初始化事件驱动
    EventBus::init();

    //设置httpClient
    CtrlServ::setHttpClient(new we7HttpClient());

    if (App::isChargingDeviceEnabled()) {
        ChargingServ::setHttpClient(new we7HttpClient());
    }

    //默认加载缓存
    We7::load()->func('cache');

    //启动应用
    app()->run();

} catch (Exception $e) {

    Log::error("app", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

}
