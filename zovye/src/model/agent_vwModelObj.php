<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use function zovye\tb;

/**
 * Class agent_vwModelObj
 * @package zovye
 * @method getDeviceTotal()
 * @method setName(string $name)
 */
class agent_vwModelObj extends agentModelObj
{
    /** @var string */
    protected $name;
    /** @var int */
    protected $device_total;

    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($read_or_write == self::OP_READ) {
            return tb('agent_vw');
        }
        trigger_error('user getTableName(...) miss op!');

        return '';
    }

    public function getName(): string
    {
        return empty($this->name) ? parent::getName() : $this->name;
    }
}
