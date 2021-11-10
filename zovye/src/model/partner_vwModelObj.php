<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use function zovye\tb;

class partner_vwModelObj extends userModelObj
{
    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($readOrWrite == self::OP_READ) {
            return tb('partner_vw');
        }
        trigger_error('user getTableName(...) miss op!');
        return '';
    }

	protected $updatetime;
}