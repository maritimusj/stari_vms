<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use function zovye\tb;

class gspor_vwModelObj extends userModelObj
{
    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($readOrWrite == self::OP_READ) {
            return tb('gspor_vw');
        }
        trigger_error('user getTableName(...) miss op!');
        return '';
    }

	protected $updatetime;
}