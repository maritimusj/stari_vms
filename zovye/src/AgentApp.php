<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

//代理商申请状态
class AgentApp extends State
{
    const WAIT = 0;
    const CHECKED = 1;

    protected static $title = [
        self::WAIT => '未处理',
        self::CHECKED => '已处理',
    ];
}
