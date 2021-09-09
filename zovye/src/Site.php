<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use WeModuleSite;

class Site extends WeModuleSite
{
    public function __call($name, $args)
    {
        $isWeb = stripos($name, 'doWeb') === 0;
        $isMobile = stripos($name, 'doMobile') === 0;

        if ($isWeb || $isMobile) {
            $dir = IA_ROOT . '/addons/' . $this->modulename . '/inc/';
            $fun = '';

            if ($isWeb) {
                $dir .= 'web/';
                $fun = strtolower(substr($name, 5));
            }

            if ($isMobile) {
                $dir .= 'mobile/';
                $fun = strtolower(substr($name, 8));
            }

            $file = $dir . $fun . '.inc.php';
            if (file_exists($file)) {
                require $file;
            } else {
                if (DEBUG) {
                    die($file . ' not exists!');
                }
            }
        }
    }
}
