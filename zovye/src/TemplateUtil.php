<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use we7\template;

class TemplateUtil
{
    public static function compile($filename)
    {
        global $_W;
        if (defined('IN_SYS')) {
            $source = ZOVYE_ROOT."template/$filename.html";
            $compile = ZOVYE_ROOT."data/tpl/$filename.tpl.php";
        } else {
            $source = ZOVYE_ROOT."template/mobile/$filename.html";
            $compile = ZOVYE_ROOT."data/tpl/mobile/$filename.tpl.php";
        }

        if (!is_file($source)) {
            exit("Error: template source '$filename' is not exist!");
        }

        $paths = pathinfo($compile);
        $compile = str_replace($paths['filename'], $_W['uniacid'].'_'.$paths['filename'], $compile);
        if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            template::compile($source, $compile, true);
        }

        return $compile;
    }

}