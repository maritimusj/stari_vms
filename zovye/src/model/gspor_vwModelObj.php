<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use function zovye\tb;

class gspor_vwModelObj extends userModelObj
{
    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($read_or_write == self::OP_READ) {
            return tb('gspor_vw');
        }
        trigger_error('user getTableName(...) miss op!');

        return '';
    }

    protected $updatetime;
}