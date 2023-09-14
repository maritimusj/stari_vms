<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\traits\DataGetterAndSetter;
use function zovye\tb;

class account_logsModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('account_logs');
    }

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