<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Agent;
use zovye\DeviceTypes;
use zovye\model\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method getAgentId()
 * @method getTitle()
 * @method setTitle(string $title)
 * @method getCreatetime()
 * @method setAgentId(int $agentId)
 * @method getDeviceId()
 */
class device_typesModelObj extends modelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $agent_id;

    /** @var int */
    protected $device_id;

    /** @var string */
    protected $title;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($read_or_write): string
    {
        return tb('device_types');
    }

    /**
     * @param bool $detail
     * @return array
     */
    public function getCargoLanes(bool $detail = false): array
    {
        return DeviceTypes::getCargoLanes($this, $detail);
    }

    public function getCargoLanesNum(): int
    {
        $cargo_lanes = $this->getExtraData('cargo_lanes', []);

        return count($cargo_lanes);
    }

    /**
     * @return agentModelObj
     */
    public function getAgent(): ?agentModelObj
    {
        return Agent::get($this->agent_id);
    }
}