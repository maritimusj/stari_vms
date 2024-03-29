<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\traits\DataGetterAndSetter;

/**
 * Class baseLogsModelObj
 * @package zovye
 * @method getLevel();
 * @method setLevel($level);
 * @method getTitle();
 * @method setTitle($title);
 * @method getCreatetime();
 */
class baseLogsModelObj extends ModelObj
{
    protected $id;
    protected $uniacid;
    protected $level;
    protected $title;
    protected $data;
    protected $createtime;

    use DataGetterAndSetter;

    static function getTableName($read_or_write): string
    {
        trigger_error("base log model obj not extended", E_USER_ERROR);
    }

    function getRawData()
    {
        return parent::getData();
    }

    function setRawData($data)
    {
        parent::setData($data);
    }
}