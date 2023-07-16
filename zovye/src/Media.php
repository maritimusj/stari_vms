<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

//广告类型
class Media extends State
{
    const IMAGE = 'image';
    const AUDIO = 'audio';
    const VIDEO = 'video';
    const SRT = "srt";

    protected static $title = [
        self::IMAGE => '图片',
        self::AUDIO => '音频',
        self::VIDEO => '视频',
        self::SRT => '字幕',
    ];

    public static function sign($url): string
    {
        return sha1(App::uid().Session::getClientIp().$url).'@'.$url;
    }

    public static function verify($signature_url): bool
    {
        list($sha1val, $url) = explode('@', $signature_url, 2);
        return !empty($sha1val) && !empty($url) && sha1(App::uid().Session::getClientIp().$url) == $sha1val;
    }

    public static function strip($signature_url)
    {
        list($sha1val, $url) = explode('@', $signature_url, 2);
        if (!empty($sha1val) && !empty($url) && sha1(App::uid().Session::getClientIp().$url) == $sha1val) {
            return $url;
        }
        return false;
    }
}
