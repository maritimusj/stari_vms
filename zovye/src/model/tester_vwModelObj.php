<?php


namespace zovye\model;


use zovye\model\base\modelObj;
use function zovye\tb;

class tester_vwModelObj extends userModelObj
{
    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == modelObj::OP_WRITE) {
            return parent::getTableName($read_or_write);
        }

        return tb('tester_vw');
    }
}