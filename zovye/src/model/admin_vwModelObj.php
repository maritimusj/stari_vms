<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;


use zovye\base\modelObj;
use function zovye\tb;

class admin_vwModelObj extends userModelObj
{
    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == modelObj::OP_WRITE) {
            return parent::getTableName($read_or_write);
        }

        return tb('admin_vw');
    }
}