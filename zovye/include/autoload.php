<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

/**
 * 加载 class
 */
spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $len = strlen($prefix);

    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $file = ZOVYE_CORE_ROOT . 'src' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $len)) . '.php';
    if (file_exists($file)) {
        include_once $file;
    }
});

/**
 * lib 加载
 */
spl_autoload_register(function ($class) {
    $file = ZOVYE_ROOT . 'lib' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (file_exists($file)) {
        include_once $file;
    }
});
