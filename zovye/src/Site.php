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
            $do = '';

            if ($isWeb) {
                $dir .= 'web/';
                $do = strtolower(substr($name, 5));
            }

            if ($isMobile) {
                $dir .= 'mobile/';
                $do = strtolower(substr($name, 8));
            }

            $new_dir = $dir.$do.'/';

            $op = Request::op('default');

            $op = toCamelCase($op);
            $file = $new_dir.$op.'.php';
            if (file_exists($file)) {
                require $file;
                return;
            }

            $op = toSnakeCase($op);
            $file = $new_dir.$op.'.php';
            if (file_exists($file)) {
                require $file;
                return;
            }

            $file = $new_dir.$do.'.inc.php';
            if (file_exists($file)) {
                require $file;
                return;
            }

            $file = $dir.$do.'.inc.php';
            if (file_exists($file)) {
                require $file;
                return;
            }

            trigger_error('invalid request: ' . $name);
        }
    }
}
