<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class UserLogs extends Base
{
    public static function model(): base\modelFactory
    {
        return m('user_logs');
    }
}