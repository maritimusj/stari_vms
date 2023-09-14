<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Agent;
use zovye\domain\Device;
use function zovye\tb;

/**
 * @method setAgentId($getAgentId)
 * @method getExpiredAt()
 * @method setExpiredAt(int $int)
 * @method setPreDays(int $int)
 * @method getPreDays()
 * @method setInvalidIfExpired(bool $v)
 * @method getInvalidIfExpired()
 * @method getLaneId()
 * @method setLaneId($lane_id)
 */
class goods_expire_alertModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('goods_expire_alert');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $agent_id;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $lane_id;

    /** @var int */
    protected $expired_at;

    /** @var int */
    protected $pre_days;

    /** @var int */
    protected $invalid_if_expired;

    /** @var int */
    protected $createtime;

    public function getAgent(): ?agentModelObj
    {
        return Agent::get($this->agent_id);
    }

    public function getDevice(): ?deviceModelObj
    {
        return Device::get($this->device_id);
    }
}