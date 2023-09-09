<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class DeviceEvents extends Base
{
    public static function model(): base\modelFactory
    {
        return m('device_events');
    }
}