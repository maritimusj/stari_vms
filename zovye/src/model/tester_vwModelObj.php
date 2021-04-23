<?php


namespace zovye\model;


use zovye\base\modelObj;
use function zovye\tb;

class tester_vwModelObj extends userModelObj
{
    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == modelObj::OP_WRITE) {
            return parent::getTableName($readOrWrite);
        }
        return tb('tester_vw');
    }
}