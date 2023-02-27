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

            $op = request::op('default');
            $this->route($op);

            $file = $dir.$fn.'.inc.php';
            if (file_exists($file)) {
                require $file;
            } else {
                if (DEBUG) {
                    die($file.' not exists!');
                }
            }
        }
    }

    public function route($op)
    {
        $dir =  IA_ROOT.'/addons/'.$this->modulename.'/inc';
        if ($this->inMobile) {
            $dir .= '/mobile';
        } else {
            $dir .= '/web';
        }

        $dir .= '/' . $GLOBALS['_GPC']['do'];
        $file = $dir . '/' . toSnakeCase(toCamelCase($op)) . '.php';

        if (file_exists($file)) {
            require($file);
        }
    }
}
