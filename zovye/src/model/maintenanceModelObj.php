<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

/**
 * Class maintenanceModelObj
 * @package zovye
 * @method getDeviceId()
 * @method getErrorCode()
 * @method getResultCode()
 * @method getMobile()
 * @method getName()
 * @method getResult()
 * @method getCreatetime()
 */
class maintenanceModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    /** @var int */
    protected $device_id;
    protected $error_code;
    protected $result_code;
    protected $mobile;
    protected $name;
    protected $result;
    /** @var int */
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('maintenance');
    }
}