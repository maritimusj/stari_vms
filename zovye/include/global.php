<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;

//定义常量REQUEST_ID
define('REQUEST_ID', Util::generateUID());

try {
    //默认加载缓存
    We7::load()->func('cache');

    Request::extraAjaxJsonData();

    //设置request数据源
    Request::setData($GLOBALS['_GPC']);

    //初始化日志
    Log::init(new FileLogWriter(), settings('app.log.level', LOG_LEVEL));

    //捕获错误和异常
    Util::setErrorHandler();

    //初始化事件驱动
    EventBus::init();

    //设置CtrlServ
    CtrlServ::init(new we7HttpClient(), settings('ctrl', []));

    //初始化充电设备服务
    if (App::isChargingDeviceEnabled()) {
        ChargingServ::setHttpClient(new we7HttpClient(), Config::charging('server', []));
    }

    //启动应用
    app()->run();

} catch (Exception $e) {

    Log::error("app", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

}
