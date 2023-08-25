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

    //启动应用
    app()->run();

} catch (Exception $e) {

    Log::error("app", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

}
