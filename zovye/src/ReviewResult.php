<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

//广告审核状态
class ReviewResult extends State
{
    const WAIT = 0;
    const PASSED = 1;
    const REJECTED = 2;

    protected static $title = [
        self::WAIT => '审核中',
        self::PASSED => '通过',
        self::REJECTED => '拒绝',
    ];
}
