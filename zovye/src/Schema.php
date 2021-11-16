<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

//领取周期
class Schema extends State
{
    const DAY = 'd';
    const WEEK = 'w';
    const MONTH = 'm';

    protected static $title = [
        self::DAY => '天',
        self::WEEK => '周',
        self::MONTH => '月',
    ];
}
