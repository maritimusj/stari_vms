<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class DeviceLogs extends Base
{
    public static function model(): base\modelFactory
    {
        return m('device_logs');
    }
}