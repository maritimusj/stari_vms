<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class DeviceLogs
{
    public static function model(): model\base\modelFactory
    {
        return m('device_logs');
    }
}