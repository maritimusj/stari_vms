<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\util\Util;

//定义常量REQUEST_ID
define('REQUEST_ID', Util::generateUID());

$vendor_autoload_filename = MODULE_ROOT.'vendor/autoload.php';
if (file_exists($vendor_autoload_filename)) {
    require_once $vendor_autoload_filename;
}

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
