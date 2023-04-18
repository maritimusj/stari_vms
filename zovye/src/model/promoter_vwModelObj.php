<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;


use zovye\base\modelObj;
use function zovye\tb;

class promoter_vwModelObj extends userModelObj
{
    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == modelObj::OP_WRITE) {
            return parent::getTableName($readOrWrite);
        }

        return tb('promoter_vw');
    }
}