<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Agent;

use function zovye\tb;
use zovye\DeviceTypes;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

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

    public static function getTableName($readOrWrite): string
    {
        return tb('device_types');
    }

    /**
     * @param bool $detail
     * @return array
     */
    public function getCargoLanes($detail = false): array
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