<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class UserLogs
{
    public static function model(): model\base\modelFactory
    {
        return m('user_logs');
    }
}