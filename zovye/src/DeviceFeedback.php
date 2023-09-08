<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class DeviceFeedback
{
    public static function model(): base\modelFactory
    {
        return m('device_feedback');
    }
}