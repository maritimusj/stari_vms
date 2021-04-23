<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
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
}
