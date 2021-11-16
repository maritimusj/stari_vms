<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use function zovye\tb;

class keeper_vwModelObj extends userModelObj
{
    /** @var int */
    protected $updatetime;
    protected $name;

    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($readOrWrite == self::OP_READ) {
            return tb('keeper_vw');
        }
        trigger_error('user getTableName(...) miss op!');
        return '';
    }

    public function getName(): string
    {
        return empty($this->name) ? parent::getName() : $this->name;
    }
}