<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use DateTimeInterface;
use zovye\Agent;
use zovye\Group;

use function zovye\tb;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * Class device_groupsModelObj
 * @package zovye
 * @method getId()
 * @method getTitle()
 * @method setTitle($title)
 * @method getTypeId()
 * @method setTypeId($title)*
 * @method getClr()
 * @method setClr($clr)
 * @method getAgentId()
 * @method getCreatetime()
 * @method setAgentId(int $agentId)
 */
class device_groupsModelObj extends modelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $type_id;

    /** @var string */
    protected $title;

    /** @var string */
    protected $clr;

    /** @var int */
    protected $agent_id;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($read_or_write): string
    {
        return tb('device_groups');
    }

    public function format($detail = true): array
    {
        return Group::format($this, $detail);
    }

    public function profile(): array
    {
        return Group::format($this);
    }

    public function getAgent(): ?agentModelObj
    {
        if ($this->agent_id > 0) {
            return Agent::get($this->agent_id);
        }

        return null;
    }

    public function getName()
    {
        return $this->getExtraData('name', '');
    }

    public function setName(string $name)
    {
        return $this->setExtraData('name', $name);
    }

    public function getDescription()
    {
        return $this->getExtraData('description', '');
    }

    public function setDescription(string $desc)
    {
        return $this->setExtraData('description', $desc);
    }

    public function getAddress(): string
    {
        return $this->getExtraData('address', '');
    }

    public function setAddress(string $address)
    {
        return $this->setExtraData('address', $address);
    }

    public function getLoc(): array
    {
        return [
            'lat' => $this->getExtraData('lat', 0.0),
            'lng' => $this->getExtraData('lng', 0.0),
        ];
    }

    public function setLoc(array $loc): bool
    {
        return $this->setExtraData('lat', $loc['lat']) && $this->setExtraData('lng', $loc['lng']);
    }

    public function setFee(array $fee)
    {
        return $this->setExtraData('fee', $fee);
    }

    public function getFee(): array
    {
        return $this->getExtraData('fee', []);
    }

    public function getServiceFee(): float {
        return floatval($this->getExtraData('fee.l0.sf', 0.0));
    }

    public function getFeeAt(DateTimeInterface $time): array
    {
        $hour = $time->format('G');

        $fee = $this->getFee();
        $level = 'l'.($fee['ts'][$hour] ?? 0);

        return (array)($fee[$level] ?? $fee['l0']);
    }

    public function setVersion($version)
    {
        return $this->setExtraData('version', $version);
    }

    public function getVersion()
    {
        return $this->getExtraData('version', 'n/a');
    }
}