<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class PayLogs
{
    public static function model(): base\modelFactory
    {
        return m('pay_logs');
    }
}