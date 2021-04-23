<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

class Theme
{
    /**
     * 获取设备页面schema列表
     * @return array
     */
    public static function all(): array
    {
        static $themes = [];
        if (empty($themes)) {
            foreach (glob(MODULE_ROOT . '/template/mobile/themes/*', GLOB_ONLYDIR) as $name) {
                $themes[] = basename($name);
            }
        }

        return $themes;
    }

    /**
     * 获取皮肤文件名，当前皮肤不包括指定文件时，则返回默认皮肤的相应文件
     * @param $name
     * @return string
     */
    public static function file($name): string
    {
        $theme = settings('device.get.theme', 'default');
        $filename = MODULE_ROOT . "/template/mobile/themes/{$theme}/{$name}.html";
        if (file_exists($filename)) {
            return "themes/{$theme}/{$name}";
        }

        if ($theme != 'default') {
            $filename = MODULE_ROOT . "/template/mobile/themes/default/{$name}.html";
            if (file_exists($filename)) {
                return "themes/default/{$name}";
            }
        }

        $filename = MODULE_ROOT . "/template/mobile/{$name}.html";
        if (file_exists($filename)) {
            return $name;
        }

        Util::logToFile('theme', [
            'theme' => $name,
            'file' => $filename,
            'error' => 'theme file not found!',
        ]);

        return 'not_found';
    }
}
