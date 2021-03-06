<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
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
            foreach (glob(MODULE_ROOT.'/template/mobile/themes/*', GLOB_ONLYDIR) as $name) {
                $themes[] = basename($name);
            }
        }

        return $themes;
    }

    public static function getThemeFile($device, $name): string
    {
        return self::file($name, Helper::getTheme($device));
    }

    /**
     * 获取皮肤文件名，当前皮肤不包括指定文件时，则返回默认皮肤的相应文件
     * @param string $theme
     * @param $name
     * @return string
     */
    public static function file($name, string $theme = ''): string
    {
        if (empty($theme)) {
            $theme = Helper::getTheme();
        }

        $filename = MODULE_ROOT."/template/mobile/themes/$theme/$name.html";
        if (file_exists($filename)) {
            return "themes/$theme/$name";
        }

        if ($theme != 'default') {
            $filename = MODULE_ROOT."/template/mobile/themes/default/$name.html";
            if (file_exists($filename)) {
                return "themes/default/$name";
            }
        }

        $filename = MODULE_ROOT."/template/mobile/$name.html";
        if (file_exists($filename)) {
            return $name;
        }

        Log::error('theme', [
            'theme' => $name,
            'file' => $filename,
            'error' => 'theme file not found!',
        ]);

        return 'not_found';
    }
}
