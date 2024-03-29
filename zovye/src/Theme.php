<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\Helper;

class Theme
{
    static $helper = [
        'shandan' => '* 闪蛋系统专用皮肤',
        'CVMachine' => '* 省避孕药具平台专用皮肤',
        'promo' => '* 国外短信领取专用皮肤',
        'march' => '* 定制功能：支持长按出货',
        'march2' => '* 定制功能：支持长按出货',
        'march3' => '* 定制功能：只支持免费领取',
    ];

    /**
     * 获取设备页面schema列表
     */
    public static function all(): array
    {
        static $themes = [];
        if (empty($themes)) {
            foreach (glob(MODULE_ROOT.'/template/mobile/themes/*', GLOB_ONLYDIR) as $dir) {
                $name = basename($dir);
                $themes[$name] = [
                    'name' => $name,
                    'helper' => self::$helper[$name] ?? '',
                ];
            }
        }

        if (!App::isFlashEggEnabled()) {
            unset($themes['shandan']);
        }

        if (!App::isGDCVMachineEnabled()) {
            unset($themes['CVMachine']);
        }

        if (!App::isPromoterEnabled()) {
            unset($themes['promo']);
        }

        return array_values($themes);
    }

    public static function valid(): array
    {
        $list = self::all();
        
        foreach ($list as $index => $theme) {
            if (!Config::app("theme.{$theme['name']}.enabled", true)) {
                unset($list[$index]);
            }
        }

        return array_values($list);
    }

    public static function getThemeFile($device, $name): string
    {
        return self::file($name, Helper::getTheme($device));
    }

    /**
     * 获取皮肤文件名，当前皮肤不包括指定文件时，则返回默认皮肤的相应文件
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

        trigger_error("theme file not found, theme: $theme, name: $name", E_USER_ERROR);
    }
}
