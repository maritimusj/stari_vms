<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use function zovye\tb;

class device_logsModelObj extends BaseLogsModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('device_logs');
    }
}