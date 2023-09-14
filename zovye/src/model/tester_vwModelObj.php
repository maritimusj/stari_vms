<?php


namespace zovye\model;


use zovye\base\ModelObj;
use function zovye\tb;

class tester_vwModelObj extends userModelObj
{
    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == ModelObj::OP_WRITE) {
            return parent::getTableName($read_or_write);
        }

        return tb('tester_vw');
    }
}