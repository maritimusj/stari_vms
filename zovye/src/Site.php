<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
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
            $dir = IA_ROOT.'/addons/'.$this->modulename.'/inc/';
            $fn = '';

            if ($isWeb) {
                $dir .= 'web/';
                $fn = strtolower(substr($name, 5));
            }

            if ($isMobile) {
                $dir .= 'mobile/';
                $fn = strtolower(substr($name, 8));
            }

            $file = $dir.$fn.'.inc.php';
            if (file_exists($file)) {
                require $file;
            }

            $op = request::op('default');

            $file = $dir.$fn.'/'.$op.'.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    }
}
