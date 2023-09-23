<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use function zovye\m;

class DeviceFeedback extends AbstractBase
{
    public static function model(): ModelFactory
    {
        return m('device_feedback');
    }
}