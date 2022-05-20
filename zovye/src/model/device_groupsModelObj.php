<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Agent;
use zovye\Group;

use function zovye\tb;
use zovye\base\modelObj;

/**
 * Class device_groupsModelObj
 * @package zovye
 * @method getId()
 * @method getTitle()
 * @method setTitle($title)
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

    /** @var string */
    protected $title;

    /** @var string */
    protected $clr;

    /** @var int */
    protected $agent_id;

    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('device_groups');
    }

    public function format(): array
    {
        return Group::format($this);
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
}