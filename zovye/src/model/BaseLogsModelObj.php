<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\traits\DataGetterAndSetter;

/**
 * Class BaseLogsModelObj
 * @package zovye
 * @method getLevel();
 * @method setLevel($level);
 * @method getTitle();
 * @method setTitle($title);
 * @method getCreatetime();
 */
class BaseLogsModelObj extends ModelObj
{
    protected $id;
    protected $uniacid;
    protected $level;
    protected $title;
    protected $data;
    protected $createtime;

    use DataGetterAndSetter;

    function getRawData()
    {
        return parent::getData();
    }

    function setRawData($data)
    {
        parent::setData($data);
    }
}